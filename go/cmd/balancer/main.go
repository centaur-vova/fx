package main

import (
	"context"
	"fast-atomic-flow/go/internal/balancer"
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/middleware"
	"fast-atomic-flow/go/internal/protocol"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/joho/godotenv"
)

// ========== CONSTANTS ==========
const (
	// Health check settings
	healthCheckTimeout         = 2 * time.Second
	healthCheckInterval        = 10 * time.Second
	healthCheckKeepAlive       = 30 * time.Second
	healthCheckIdleConnTimeout = 90 * time.Second
	healthCheckMaxIdleConns    = 10
	healthCheckPath            = "/health"

	// Instance lifecycle
	instanceTTL     = 30 * time.Second
	cleanupInterval = 30 * time.Second

	// Server
	shutdownTimeout = 5 * time.Second

	// Balancer
	defaultPort = "8080"
)

// ========== GLOBALS ==========
var (
	cfg      *protocol.BalancerConfig
	upstream *balancer.Upstream
)

// ========== MAIN ==========

func main() {
	// Load .env file
	godotenv.Load("../.env")

	// Load configuration (API tokens etc)
	cfg = protocol.LoadBalancerConfig()

	// Initialize logger
	logger.Init(cfg.LogLevel)

	// Upstream config
	upstreamCfg := balancer.Config{
		InstanceTTL:     instanceTTL,
		CleanupInterval: cleanupInterval,
		HealthCheck: balancer.HealthCheckConfig{
			Timeout:         healthCheckTimeout,
			Interval:        healthCheckInterval,
			KeepAlive:       healthCheckKeepAlive,
			IdleConnTimeout: healthCheckIdleConnTimeout,
			MaxIdleConns:    healthCheckMaxIdleConns,
			Path:            healthCheckPath,
		},
	}
	upstream = balancer.NewUpstream(upstreamCfg)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Start background goroutines
	go upstream.HealthCheck(ctx)
	go upstream.CleanupDeadInstances(ctx)

	// Setup router
	mux := http.NewServeMux()

	// Health
	mux.HandleFunc("GET /health", balancer.HealthHandler(upstream))
	// Register API instance (called by API service)
	mux.HandleFunc("POST /instance/register", middleware.AuthMiddleware(cfg.APIToken, balancer.RegisterHandler(upstream)))
	// Force alive/unalive state on the API instance
	mux.HandleFunc("POST /instance/unalive", balancer.ForceUnaliveHandler(upstream))
	mux.HandleFunc("POST /instance/revive", balancer.ReviveHandler(upstream))
	// Proxy
	mux.HandleFunc("/", balancer.ProxyHandler(upstream))

	// Server
	port := cfg.BalancerPort
	if port == "" {
		port = defaultPort
	}
	server := http.Server{
		Addr:    ":" + port,
		Handler: mux,
	}

	// Start server in goroutine
	go func() {
		logger.Info("🐎 Balancer service started", "addr", ":"+port)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Emergency("💥 Balancer crashed", "error", err)
			os.Exit(1)
		}
	}()

	// Wait for shutdown signal
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logger.Info("🛑 Shutting down...")

	// Cancel the context for background goroutines
	cancel()

	// Graceful shutdown with timeout
	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), shutdownTimeout)
	defer shutdownCancel()

	if err := server.Shutdown(shutdownCtx); err != nil {
		logger.Error("💥 Shutdown error", "error", err)
	}

	logger.Info("🛑 Stopped")
}
