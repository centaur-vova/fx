package gateway

import (
	"encoding/json"
	"errors"
	"fast-atomic-flow/go/internal/protocol"
	"log"
	"math"
	"net/http"
	"runtime"
	"sync"
	"time"

	"github.com/gorilla/websocket"
	"github.com/shirou/gopsutil/v3/cpu"
)

type Hub struct {
	clients   map[*websocket.Conn]*sync.Mutex
	clientsMu sync.RWMutex
	config    *protocol.AppConfig
	upgrader  websocket.Upgrader
}

func NewHub(cfg *protocol.AppConfig) *Hub {
	return &Hub{
		clients: make(map[*websocket.Conn]*sync.Mutex),
		config:  cfg,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(r *http.Request) bool { return true },
		},
	}
}

func (h *Hub) Add(conn *websocket.Conn) {
	h.clientsMu.Lock()
	h.clients[conn] = &sync.Mutex{}
	h.clientsMu.Unlock()
}

func (h *Hub) WriteToConn(conn *websocket.Conn, kind int, payload []byte) error {
	h.clientsMu.RLock()
	lock, exists := h.clients[conn]
	h.clientsMu.RUnlock()

	if !exists {
		return errors.New("client not found")
	}

	lock.Lock()
	defer lock.Unlock()
	return conn.WriteMessage(kind, payload)
}

func (h *Hub) Remove(conn *websocket.Conn) {
	h.clientsMu.Lock()
	delete(h.clients, conn)
	h.clientsMu.Unlock()
}

func (h *Hub) Count() int {
	h.clientsMu.RLock()
	defer h.clientsMu.RUnlock()
	return len(h.clients)
}

func (h *Hub) Broadcast(data any) {
	var payload []byte
	var kind int

	// Binary format?
	if packer, ok := data.(protocol.BinaryPacker); ok {
		payload = packer.Pack()
		kind = websocket.BinaryMessage
	} else {
		// Json format
		payload, _ = json.Marshal(data)
		kind = websocket.TextMessage
	}

	h.clientsMu.RLock()
	defer h.clientsMu.RUnlock()

	for conn, lock := range h.clients {
		lock.Lock()
		_ = conn.WriteMessage(kind, payload)
		lock.Unlock()
	}
}

func (h *Hub) HandleWS(w http.ResponseWriter, r *http.Request, router *Router) {
	conn, err := h.upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Printf("WS Upgrade error: %v", err)
		return
	}

	// Register connection
	h.Add(conn)
	log.Printf("New client. Total: %d", h.Count())

	defer func() {
		h.Remove(conn)
		conn.Close()
		log.Printf("Disconnected. Remaining: %d", h.Count())
	}()

	// Create and send welcome event
	welcomeEvent := protocol.NewEvent(
		"welcome",
		protocol.WelcomeData{
			WorkerNum:     h.config.WorkerNum,
			CPUCores:      h.config.CPUCores,
			QueueCapacity: h.config.QueueCapacity,
			AppVersion:    h.config.AppVersion,
		},
	)
	h.WriteToConn(conn, websocket.TextMessage, welcomeEvent.Marshal())

	// Loop + pong
	for {
		messageType, p, err := conn.ReadMessage()
		if err != nil {
			break
		}

		// Text message received - try routing it
		if messageType == websocket.TextMessage {
			response, err := router.Route(p)
			if err != nil {
				log.Printf("Router error: %v", err)
				continue
			}

			if response != nil {
				// Send response back
				h.WriteToConn(conn, websocket.TextMessage, response)
			}
		}
	}
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

		metrics := protocol.NewEvent(
			"metrics.update",
			protocol.SystemMetrics{
				Connections: len(h.clients),
				MemoryMb:    memRounded,
				CPUUsage:    cpuRounded,
			},
		)

		h.Broadcast(metrics)
	}
}
