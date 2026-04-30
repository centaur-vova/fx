package main

import (
	"context"
	"encoding/json"
	"fast-atomic-flow/go/internal/gateway"
	"fast-atomic-flow/go/internal/protocol"
	"fast-atomic-flow/go/internal/semaphore"
	"fmt"
	"log"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"sync"
	"syscall"
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
	cfg       *protocol.AppConfig
	clients   = make(map[*websocket.Conn]bool)
	clientsMu sync.Mutex
	upgrader  = websocket.Upgrader{
		CheckOrigin: func(r *http.Request) bool { return true }, // Allow all
	}
	nc    *nats.Conn
	sub   *nats.Subscription
	subMu sync.Mutex
	hub   *gateway.Hub
)

func subscribeToNATS() {
	subMu.Lock()
	defer subMu.Unlock()

	// Unsubscribe if subscribed
	if sub != nil {
		sub.Unsubscribe()
	}

	var err error
	sub, err = nc.Subscribe(cfg.BroadcastCh, func(m *nats.Msg) {
		defer func() {
			if r := recover(); r != nil {
				log.Printf("Panic in NATS handler: %v", r)
			}
		}()

		var env struct {
			Type string          `json:"_t"`
			Data json.RawMessage `json:"d"`
		}
		json.Unmarshal(m.Data, &env)

		log.Printf("NATS -> WS: subject=%s, data=%s", m.Subject, string(m.Data))

		if env.Type == "task.status.update" {
			var t protocol.TaskStatusUpdate
			if err := json.Unmarshal(env.Data, &t); err == nil {
				hub.Broadcast(&t)
			}
		}
	})
	if err != nil {
		log.Printf("Subscribe error: %v", err)
	} else {
		log.Printf("Subscribed to %s", cfg.BroadcastCh)
	}
}

func main() {
	// ==== LOAD .env ====
	if err := godotenv.Load("../.env"); err != nil {
		log.Println("No .env file found, using system env")
	}

	// ==== LOAD CONFIG ====
	cfg = protocol.LoadConfig()
	cfg.Validate()

	// === LOGGER ===
	opts := &slog.HandlerOptions{
		Level: slog.LevelDebug,
	}
	logger := slog.New(slog.NewTextHandler(os.Stdout, opts))
	slog.SetDefault(logger)

	// ==== INIT ROUTER ====
	router := gateway.NewRouter()

	// ==== NATS ====
	var err error
	nc, err = nats.Connect(
		"nats://"+cfg.NatsURL,
		nats.Token(cfg.NatsToken),
		nats.MaxReconnects(-1),            // Retry forever
		nats.ReconnectWait(2*time.Second), // Every 2 second
		nats.DisconnectErrHandler(func(c *nats.Conn, err error) {
			log.Printf("⚠️ NATS DISCONNECTED: %v", err)
		}),
		nats.ReconnectHandler(func(c *nats.Conn) {
			log.Printf("✅ NATS RECONNECTED to %s", c.ConnectedUrl())
			// Need to resubscribe
		}),
		nats.ClosedHandler(func(c *nats.Conn) {
			log.Printf("🔴 NATS connection CLOSED")
		}),
	)
	if err != nil {
		log.Fatalf("NATS Connection failed: %v", err)
	}
	defer nc.Close()

	fmt.Printf("Go Proxy connected to NATS at %s\n", cfg.NatsURL)

	// ==== INIT HUB ====
	hub = gateway.NewHub(cfg, nc)

	// ==== NATS - passthrough incoming messages to websockets =====
	subscribeToNATS()

	// Broadcast metrics every two seconds
	go hub.RunMetricsBroadcaster(2 * time.Second)

	// === WS HANDLER ===
	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if r := recover(); r != nil {
				log.Printf("Panic in WebSocket endpoint: %v", r)
				http.Error(w, "Internal Server Error", http.StatusInternalServerError)
			}
		}()
		hub.HandleWS(w, r, router)
	})

	// === HTTP HANDLERS ===
	semPool := semaphore.NewPool()
	semHandler := semaphore.NewHandler(semPool)
	http.HandleFunc("/semaphore/acquire", semHandler.AuthMiddleware(cfg.APIToken, semHandler.Acquire))
	http.HandleFunc("/semaphore/release", semHandler.AuthMiddleware(cfg.APIToken, semHandler.Release))

	// ==== INIT WEBSOCKET SERVER ====
	srv := &http.Server{
		Addr:    ":" + cfg.WSPort,
		Handler: nil,
	}

	// ==== RUN WS SERVER IN GOROUTINE ====
	go func() {
		log.Printf("WebSocket Gateway ready on :%s/ws\n", cfg.WSPort)
		log.Printf("Semaphore service started on :%s\n", cfg.WSPort)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("Server error: %v", err)
		}
	}()

	// ==== WAIT FOR QUIT SIGNAL ====
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Printf("Shutting down...")

	// === GRACEFUL SHUTDOWN ===
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	// === STOP SERVER ===
	if err := srv.Shutdown(ctx); err != nil {
		log.Printf("Shutdown error: %v", err)
	}

	// === PUT NATS INTO A DRAIN STATE ===
	nc.Drain()

	log.Printf("Stopped")
}
