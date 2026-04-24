package gateway

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/protocol"
	"log"
	"math"
	"net/http"
	"runtime"
	"sync"
	"time"

	"github.com/gorilla/websocket"
	"github.com/nats-io/nats.go"
	"github.com/shirou/gopsutil/v3/cpu"
)

type ClientMessage struct {
	Kind    int // websocket.BinaryMessage или websocket.TextMessage
	Payload []byte
}
type Client struct {
	Conn *websocket.Conn
	Send chan ClientMessage
}
type Hub struct {
	clients   map[*Client]bool
	clientsMu sync.RWMutex
	config    *protocol.AppConfig
	upgrader  websocket.Upgrader
	nc        *nats.Conn
}

func NewHub(cfg *protocol.AppConfig, nc *nats.Conn) *Hub {
	h := &Hub{
		clients: make(map[*Client]bool),
		config:  cfg,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(r *http.Request) bool { return true },
		},
		nc: nc,
	}

	return h
}

func (h *Hub) Add(conn *websocket.Conn) {
	client := &Client{
		Conn: conn,
		Send: make(chan ClientMessage, 256),
	}
	h.clientsMu.Lock()
	h.clients[client] = true
	h.clientsMu.Unlock()
	go h.writePump(client)
}

func (h *Hub) writePump(client *Client) {
	defer func() {
		h.Remove(client)
		client.Conn.Close()
	}()
	for msg := range client.Send {
		err := client.Conn.WriteMessage(msg.Kind, msg.Payload)
		if err != nil {
			return
		}
	}
}

func (h *Hub) Remove(client *Client) {
	h.clientsMu.Lock()
	if _, exists := h.clients[client]; exists {
		delete(h.clients, client)
		h.clientsMu.Unlock()
		close(client.Send)
	} else {
		h.clientsMu.Unlock()
	}
}

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
			go h.Remove(client)
		}
	}
}

func (h *Hub) Count() int {
	h.clientsMu.RLock()
	defer h.clientsMu.RUnlock()
	return len(h.clients)
}

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

func (h *Hub) HandleWS(w http.ResponseWriter, r *http.Request, router *Router) {
	conn, err := h.upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Printf("WS Upgrade error: %v", err)
		return
	}

	client := &Client{
		Conn: conn,
		Send: make(chan ClientMessage, 256),
	}

	h.clientsMu.Lock()
	h.clients[client] = true
	h.clientsMu.Unlock()
	go h.writePump(client)

	log.Printf("New client. Total: %d", h.Count())

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
			log.Printf("Router error: %v", err)
			continue
		}

		if response != nil {
			h.SendToClient(client, response)
		}
	}

	h.Remove(client)
}

func (h *Hub) RunMetricsBroadcaster(interval time.Duration) {
	ticker := time.NewTicker(interval)
	for range ticker.C {
		// RAM
		var m runtime.MemStats
		runtime.ReadMemStats(&m)
		memoryMb := float64(m.Alloc) / 1024 / 1024

		// Avg cpu usage
		c, _ := cpu.Percent(0, false)
		var cpuPercent float64
		if len(c) > 0 {
			cpuPercent = c[0]
		}

		cpuRounded := math.Round(cpuPercent*100) / 100
		memRounded := math.Round(memoryMb*100) / 100

		// NATS stats
		js, _ := h.nc.JetStream()
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
				Connections: len(h.clients),
				MemoryMb:    memRounded,
				CPUUsage:    cpuRounded,
				NatsStats:   natsStats,
			},
		)

		h.Broadcast(metrics)
	}
}
