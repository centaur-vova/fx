package main

import (
	"context"
	"encoding/json"
	"fast-atomic-flow/go/internal/gateway"
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/metrics"
	"fast-atomic-flow/go/internal/protocol"
	"net/http"
	"os"
	"os/signal"
	"sync"
	"syscall"
	"time"

	"github.com/joho/godotenv"
	"github.com/nats-io/nats.go"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

var (
	cfg   *protocol.WSConfig
	nc    *nats.Conn
	sub   *nats.Subscription
	subMu sync.Mutex
	hub   *gateway.Hub
)

// Wrapper for incoming serialized messages from NATS
type NatsEnvelope struct {
	Type string          `json:"_t"`
	Data json.RawMessage `json:"d"`
}

func main() {
	// ==== LOAD .env ====
	godotenv.Load("../.env")

	// ==== LOAD CONFIG ====
	cfg = protocol.LoadWSConfig()
	cfg.Validate()

	// === LOGGER ===
	logger.Init(cfg.LogLevel)

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
			logger.Warn("⚠️ NATS DISCONNECTED", "error", err)
		}),
		nats.ReconnectHandler(func(c *nats.Conn) {
			logger.Info("✅ NATS RECONNECTED", "url", c.ConnectedUrl())
			// Need to resubscribe
		}),
		nats.ClosedHandler(func(c *nats.Conn) {
			logger.Info("🔴 NATS connection CLOSED")
		}),
	)
	if err != nil {
		logger.Error("💥 NATS Connection failed", "error", err)
		panic(err)
	}
	defer nc.Close()

	logger.Info("✅ Go Proxy connected to NATS", "url", cfg.NatsURL)

	// ==== STORE ====
	store := metrics.NewStore()

	// ==== INIT HUB ====
	hub = gateway.NewHub(cfg, nc)

	// ==== MESSAGE ROUTER ====
	mRouter := gateway.NewMessageRouter(store, hub)

	// ==== NATS - passthrough incoming messages to websockets =====
	subscribeToNATS(mRouter)

	// Broadcast metrics every two seconds
	go hub.RunMetricsBroadcaster(time.Duration(cfg.MetricsUpdateIntervalMs) * time.Millisecond)

	// === WS HANDLER ===
	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if r := recover(); r != nil {
				logger.Error("💥 Panic in WebSocket endpoint", "panic", r)
				http.Error(w, "Internal Server Error", http.StatusInternalServerError)
			}
		}()
		hub.HandleWS(w, r, router)
	})

	// === HTTP HANDLERS ===
	// Metrics
	http.Handle("/metrics", promhttp.Handler())

	// ==== INIT WEBSOCKET SERVER ====
	srv := &http.Server{
		Addr:        ":" + cfg.WSPort,
		Handler:     nil,
		IdleTimeout: 60 * time.Second, // keep idle connections alive long enough for Swoole heartbeat
	}

	// ==== RUN WS SERVER IN GOROUTINE ====
	go func() {
		logger.Info("🚀 WebSocket Gateway ready", "port", cfg.WSPort)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Error("💥 Server crashed", "error", err)
			os.Exit(1)
		}
	}()

	// ==== WAIT FOR QUIT SIGNAL ====
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logger.Info("🛑 Shutting down...")

	// === GRACEFUL SHUTDOWN ===
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	// === STOP SERVER ===
	if err := srv.Shutdown(ctx); err != nil {
		logger.Error("💥 Shutdown error", "error", err)
	}

	// === PUT NATS INTO A DRAIN STATE ===
	nc.Drain()

	logger.Info("🔴 Stopped")
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
				logger.Error("💥 Panic in NATS handler", "recover", r)
			}
		}()

		var env struct {
			Type string          `json:"_t"`
			Data json.RawMessage `json:"d"`
		}
		json.Unmarshal(m.Data, &env)

		logger.Trace("NATS -> WS", "subject", m.Subject, "type", env.Type, "data", string(m.Data))

		mRouter.Route(env.Type, env.Data)
	})
	if err != nil {
		logger.Error("💥 Subscribe error", "error", err, "channel", cfg.BroadcastCh)
	} else {
		logger.Info("📡 Subscribed to channel", "channel", cfg.BroadcastCh)
	}
}
