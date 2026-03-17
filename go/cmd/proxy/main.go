package main

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/gateway"
	"fast-atomic-flow/go/internal/protocol"
	"fmt"
	"log"
	"net/http"
	"sync"
	"time"

	"github.com/gorilla/websocket"
	"github.com/joho/godotenv"
	"github.com/nats-io/nats.go"
)

// Wrapper for incoming serialized messages from NATS
type NatsEnvelope struct {
	Type string          `json:"_t"`
	Data json.RawMessage `json:"d"`
}

var (
	clients   = make(map[*websocket.Conn]bool)
	clientsMu sync.Mutex
	upgrader  = websocket.Upgrader{
		CheckOrigin: func(r *http.Request) bool { return true }, // Allow all
	}
)

func main() {
	// ==== LOAD .env ====
	if err := godotenv.Load("../.env"); err != nil {
		log.Println("No .env file found, using system env")
	}

	// ==== LOAD CONFIG ====
	cfg := protocol.LoadConfig()
	cfg.Validate()

	// ==== INIT HUB ====
	hub := gateway.NewHub(cfg)

	// ==== INIT ROUTER ====
	router := gateway.NewRouter()

	// ==== NATS ====
	nc, err := nats.Connect("nats://"+cfg.NatsURL, nats.Token(cfg.NatsToken))
	if err != nil {
		log.Fatalf("NATS Connection failed: %v", err)
	}
	defer nc.Close()

	fmt.Printf("Go Proxy connected to NATS at %s\n", cfg.NatsURL)

	// ==== NATS - passthrough incoming messages to websockets =====
	nc.Subscribe(cfg.BroadcastCh, func(m *nats.Msg) {
		var env struct {
			Type string          `json:"_t"`
			Data json.RawMessage `json:"d"`
		}
		json.Unmarshal(m.Data, &env)

		if env.Type == "task.status.update" {
			var t protocol.TaskStatusUpdate
			if err := json.Unmarshal(env.Data, &t); err == nil {
				hub.Broadcast(&t)
			}
		}
	})

	// Broadcast metrics every two seconds
	go hub.RunMetricsBroadcaster(2 * time.Second)

	// ==== INIT WEBSOCKET SERVER ====
	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		hub.HandleWS(w, r, router)
	})

	fmt.Printf("WebSocket Gateway ready on %s/ws\n", cfg.WSPort)
	log.Fatal(http.ListenAndServe(":"+cfg.WSPort, nil))
}
