// Package gateway implements WebSocket hub for real-time client communication.
package gateway

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/protocol"
	"fast-atomic-flow/go/internal/psychotype"
	"math"
	"net/http"
	"sync"
	"sync/atomic"
	"time"

	"github.com/gorilla/websocket"
	"github.com/nats-io/nats.go"
	"github.com/shirou/gopsutil/v3/cpu"
)

// clientSendBufferSize is the size of the send channel buffer per client.
const clientSendBufferSize = 1024

// ClientMessage wraps a WebSocket message with its type.
type ClientMessage struct {
	Kind    int // websocket.BinaryMessage или websocket.TextMessage
	Payload []byte
}

// Client represents a connected WebSocket client.
type Client struct {
	Conn   *websocket.Conn
	Send   chan ClientMessage
	closed atomic.Bool
}

// Hub manages WebSocket clients and broadcasts messages.
type Hub struct {
	clients     map[*Client]bool
	clientsMu   sync.RWMutex
	config      *protocol.WSConfig
	upgrader    websocket.Upgrader
	nc          *nats.Conn
	psyDetector *psychotype.Detector
}

// NewHub creates a new WebSocket hub.
func NewHub(cfg *protocol.WSConfig, nc *nats.Conn) *Hub {
	h := &Hub{
		clients: make(map[*Client]bool),
		config:  cfg,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(_ *http.Request) bool { return true },
		},
		nc:          nc,
		psyDetector: psychotype.NewDetector(),
	}

	return h
}

// GetClientsCount returns the current number of connected clients.
func (h *Hub) GetClientsCount() int {
	h.clientsMu.RLock()
	defer h.clientsMu.RUnlock()
	return len(h.clients)
}

// writePump sends messages from the client's send channel to the WebSocket connection.
func (h *Hub) writePump(client *Client) {
	defer func() {
		if r := recover(); r != nil {
			logger.Error("Panic in writePump", "error", r)
		}
	}()

	defer func() {
		h.Remove(client)
		if err := client.Conn.Close(); err != nil {
			logger.Warn("Error closing WebSocket client connection", "error", err)
		}
	}()
	for msg := range client.Send {
		err := client.Conn.WriteMessage(msg.Kind, msg.Payload)
		if err != nil {
			return
		}
	}
}

// Remove removes a client from the hub and closes its channels.
func (h *Hub) Remove(client *Client) {
	if !client.closed.CompareAndSwap(false, true) {
		// Already closed
		return
	}

	h.clientsMu.Lock()
	delete(h.clients, client)
	h.clientsMu.Unlock()

	close(client.Send)
}

// Broadcast sends a message to all connected clients.
func (h *Hub) Broadcast(data any) {
	var msg ClientMessage
	if packer, ok := data.(protocol.BinaryPacker); ok {
		msg.Kind = websocket.BinaryMessage
		msg.Payload = packer.Pack()
	} else {
		msg.Kind = websocket.TextMessage
		msg.Payload, _ = json.Marshal(data)
	}

	h.clientsMu.RLock()
	defer h.clientsMu.RUnlock()

	for client := range h.clients {
		select {
		case client.Send <- msg:
		default:
			// This pony can't keep up with the herd, remove it
			go h.Remove(client)
		}
	}
}

// Count returns the number of connected clients.
func (h *Hub) Count() int {
	h.clientsMu.RLock()
	defer h.clientsMu.RUnlock()
	return len(h.clients)
}

// SendToClient sends a message to a specific client.
func (h *Hub) SendToClient(client *Client, data any) {
	var msg ClientMessage
	switch v := data.(type) {
	case protocol.BinaryPacker:
		msg.Kind = websocket.BinaryMessage
		msg.Payload = v.Pack()
	case []byte:
		msg.Kind = websocket.BinaryMessage
		msg.Payload = v
	default:
		msg.Kind = websocket.TextMessage
		msg.Payload, _ = json.Marshal(v)
	}

	select {
	case client.Send <- msg:
	default:
		go h.Remove(client)
	}
}

// HandleWS upgrades HTTP to WebSocket and manages the client connection.
func (h *Hub) HandleWS(w http.ResponseWriter, r *http.Request, router *Router) {
	conn, err := h.upgrader.Upgrade(w, r, nil)
	if err != nil {
		logger.Error("WebSocket upgrade error", "error", err)
		return
	}

	client := &Client{
		Conn: conn,
		Send: make(chan ClientMessage, clientSendBufferSize),
	}

	defer func() {
		if r := recover(); r != nil {
			logger.Error("Panic in HandleWS", "error", r)
			h.Remove(client)
		}
	}()

	h.clientsMu.Lock()
	h.clients[client] = true
	h.clientsMu.Unlock()
	go h.writePump(client)

	logger.Info("New client connected", "total", h.Count())

	// Gather details about JetStream
	// TODO: Cache StreamInfo with short TTL (e.g., 10s) to reduce NATS requests on high load.
	var streamCreatedAt time.Time
	js, _ := h.nc.JetStream()
	info, err := js.StreamInfo(h.config.StreamCh)
	if err == nil && info != nil {
		streamCreatedAt = info.Created
	}

	// Welcome event
	welcomeEvent := protocol.NewEvent(
		"welcome",
		protocol.WelcomeData{
			WorkerNum:       h.config.WorkerNum,
			CPUCores:        h.config.CPUCores,
			QueueCapacity:   h.config.QueueCapacity,
			AppVersion:      h.config.AppVersion,
			BuildDate:       h.config.BuildDate,
			WorkerLabel:     h.psyDetector.Type(r.RemoteAddr),
			StreamCreatedAt: streamCreatedAt.Local().Format("2006-01-02 15:04:05"),
		},
	)
	h.SendToClient(client, welcomeEvent)

	// Read loop
	for {
		_, p, err := conn.ReadMessage()
		if err != nil {
			break
		}

		response, err := router.Route(p)
		if err != nil {
			logger.Error("Router error", "error", err)
			continue
		}

		if response != nil {
			h.SendToClient(client, response)
		}
	}

	h.Remove(client)
}

// RunMetricsBroadcaster periodically broadcasts system metrics to all clients.
func (h *Hub) RunMetricsBroadcaster(interval time.Duration) {
	ticker := time.NewTicker(interval)
	defer ticker.Stop()

	for range ticker.C {
		// RAM
		memoryMb, err := GetVMRSS()
		if err != nil {
			memoryMb = 0
		}
		freeMemory, err := GetFreeMemory()
		if err != nil {
			freeMemory = 0
		}

		// Avg cpu usage
		c, _ := cpu.Percent(0, false)
		var cpuPercent float64
		if len(c) > 0 {
			cpuPercent = c[0]
		}

		cpuRounded := math.Round(cpuPercent*100) / 100
		memRounded := math.Round(memoryMb*100) / 100
		freeMemRounded := math.Round(freeMemory*100) / 100

		// NATS stats
		js, err := h.nc.JetStream()
		if err != nil {
			logger.Error("Failed to get JetStream context", "error", err)
		}
		streamInfo, _ := js.StreamInfo(h.config.StreamCh)
		var natsStats protocol.NatsStats
		if streamInfo != nil {
			natsStats = protocol.NatsStats{
				Messages:  streamInfo.State.Msgs,
				Bytes:     streamInfo.State.Bytes,
				Consumers: streamInfo.State.Consumers,
			}
		}

		metrics := protocol.NewEvent(
			"metrics.update",
			protocol.SystemMetrics{
				Connections: h.GetClientsCount(),
				MemoryMb:    memRounded,
				FreeMemMb:   freeMemRounded,
				CPUUsage:    cpuRounded,
				NatsStats:   natsStats,
			},
		)

		h.Broadcast(metrics)
	}
}
