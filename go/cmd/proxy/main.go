package main

import (
	"context"
	"encoding/json"
	"fast-atomic-flow/go/internal/gateway"
	"fast-atomic-flow/go/internal/metrics"
	"fast-atomic-flow/go/internal/protocol"
	"fast-atomic-flow/go/internal/semaphore"
	"log"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"strings"
	"sync"
	"syscall"
	"time"

	"github.com/gorilla/websocket"
	"github.com/joho/godotenv"
	"github.com/nats-io/nats.go"
	"github.com/prometheus/client_golang/prometheus/promhttp"
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

var levelMap = map[string]slog.Level{
	"debug":     slog.LevelDebug,
	"info":      slog.LevelInfo,
	"notice":    slog.LevelInfo,
	"warning":   slog.LevelWarn,
	"error":     slog.LevelError,
	"critical":  slog.LevelError + 2,
	"alert":     slog.LevelError + 4,
	"emergency": slog.LevelError + 8,
}

func subscribeToNATS(mRouter *gateway.MessageRouter) {
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
				slog.Error("Panic in NATS handler", "recover", r)
			}
		}()

		var env struct {
			Type string          `json:"_t"`
			Data json.RawMessage `json:"d"`
		}
		json.Unmarshal(m.Data, &env)

		slog.Debug("NATS -> WS", "subject", m.Subject, "type", env.Type, "data", string(m.Data))

		mRouter.Route(env.Type, env.Data)
	})
	if err != nil {
		slog.Error("Subscribe error", "error", err, "channel", cfg.BroadcastCh)
	} else {
		slog.Info("Subscribed to channel", "channel", cfg.BroadcastCh)
	}
}

func main() {
	// ==== LOAD .env ====
	if err := godotenv.Load("../.env"); err != nil {
		slog.Info("No .env file found, using system env")
	}

	// ==== LOAD CONFIG ====
	cfg = protocol.LoadConfig()
	cfg.Validate()

	// === LOGGER ===
	level, ok := levelMap[strings.ToLower(cfg.LogLevel)]
	if !ok {
		log.Printf("unknown LOG_LEVEL '%s'", cfg.LogLevel)
		level = slog.LevelInfo
	}
	opts := &slog.HandlerOptions{
		Level: level,
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
			slog.Warn("⚠️ NATS DISCONNECTED", "error", err)
		}),
		nats.ReconnectHandler(func(c *nats.Conn) {
			slog.Info("✅ NATS RECONNECTED", "url", c.ConnectedUrl())
			// Need to resubscribe
		}),
		nats.ClosedHandler(func(c *nats.Conn) {
			slog.Info("🔴 NATS connection CLOSED")
		}),
	)
	if err != nil {
		slog.Error("NATS Connection failed", "error", err)
	}
	defer nc.Close()

	slog.Info("Go Proxy connected to NATS", "url", cfg.NatsURL)

	// ==== STORE ====
	store := metrics.NewStore()

	// ==== INIT HUB ====
	hub = gateway.NewHub(cfg, nc)

	// ==== MESSAGE ROUTER ====
	mRouter := gateway.NewMessageRouter(store, hub)

	// ==== NATS - passthrough incoming messages to websockets =====
	subscribeToNATS(mRouter)

	// Broadcast metrics every two seconds
	go hub.RunMetricsBroadcaster(2 * time.Second)

	// === WS HANDLER ===
	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if r := recover(); r != nil {
				slog.Error("Panic in WebSocket endpoint", "panic", r)
				http.Error(w, "Internal Server Error", http.StatusInternalServerError)
			}
		}()
		hub.HandleWS(w, r, router)
	})

	// === HTTP HANDLERS ===
	semPool := semaphore.NewPool()
	semHandler := semaphore.NewHandler(semPool)

	// Semaphore
	http.HandleFunc("/semaphore/acquire", semHandler.AuthMiddleware(cfg.APIToken, semHandler.Acquire))
	http.HandleFunc("/semaphore/release", semHandler.AuthMiddleware(cfg.APIToken, semHandler.Release))

	// Metrics
	http.Handle("/metrics", promhttp.Handler())

	// ==== INIT WEBSOCKET SERVER ====
	srv := &http.Server{
		Addr:    ":" + cfg.WSPort,
		Handler: nil,
	}

	// ==== RUN WS SERVER IN GOROUTINE ====
	go func() {
		slog.Info("WebSocket Gateway ready", "port", cfg.WSPort)
		slog.Info("Semaphore service started", "port", cfg.WSPort)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			slog.Error("Server crashed", "error", err)
			os.Exit(1)
		}
	}()

	// ==== WAIT FOR QUIT SIGNAL ====
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	slog.Info("Shutting down...")

	// === GRACEFUL SHUTDOWN ===
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	// === STOP SERVER ===
	if err := srv.Shutdown(ctx); err != nil {
		slog.Error("Shutdown error", "error", err)
	}

	// === PUT NATS INTO A DRAIN STATE ===
	nc.Drain()

	slog.Info("Stopped")
}
