package main

import (
	"context"
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/protocol"
	"fast-atomic-flow/go/internal/semaphore"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"github.com/joho/godotenv"
	"github.com/redis/go-redis/v9"
)

// ========== CONSTANTS ==========
const (
	// Registration settings
	registrationRetryInterval = 3 * time.Second
	registrationTimeout       = 2 * time.Second
	registrationHeartbeat     = 20 * time.Second // must be less than Balancer's instanceTTL (30s)

	// Server
	serverIdleTimeout  = 60 * time.Second
	serverReadTimeout  = 10 * time.Second
	serverWriteTimeout = 10 * time.Second
	shutdownTimeout    = 5 * time.Second

	// Health check
	healthCheckPath = "/health"
)

// ========== GLOBALS ==========
var (
	cfg *protocol.APIConfig
)

// ========== REGISTRATION ==========

// registerUpstream periodically registers this API instance with the balancer
func registerUpstream(ctx context.Context) {
	client := http.Client{Timeout: registrationTimeout}
	registerURL := cfg.BalancerURL + "/register"

	hostname, _ := os.Hostname()
	targetURL := fmt.Sprintf("http://%s:%s", hostname, cfg.APIPort)

	ticker := time.NewTicker(registrationHeartbeat)
	defer ticker.Stop()

	// Keep current connection state
	isAlive := false

	doRegister := func() bool {
		req, err := http.NewRequest("POST", registerURL, strings.NewReader(targetURL))
		if err != nil {
			// Error creating request - not a balancer issue, don't change isAlive
			return false
		}
		req.Header.Set("Authorization", "Bearer "+cfg.APIToken)

		resp, err := client.Do(req)
		if resp != nil {
			defer resp.Body.Close()
		}

		ok := err == nil && resp.StatusCode == http.StatusOK

		// Log only on state change
		if ok && !isAlive {
			logger.Info("🚀 Reconnected to balancer", "instance", targetURL)
		} else if !ok && isAlive {
			logger.Warn("⚠️ Lost connection to balancer", "instance", targetURL)
		}
		isAlive = ok
		return ok
	}

	// First registration (must succeed, retry until it does)
	for !doRegister() {
		logger.Warn("⏳ Balancer unavailable. Retrying registration...", "retry_interval", registrationRetryInterval)
		select {
		case <-ctx.Done():
			return
		case <-time.After(registrationRetryInterval):
		}
	}

	// Heartbeat loop
	for {
		select {
		case <-ctx.Done():
			logger.Info("🛑 Registration stopped")
			return
		case <-ticker.C:
			doRegister()
		}
	}
}

// ========== MAIN ==========

func main() {
	// Load .env file
	godotenv.Load("../.env")

	// Load configuration
	cfg = protocol.LoadAPIConfig()

	// Initialize logger
	logger.Init(cfg.LogLevel)

	// Redis client
	redisClient := redis.NewClient(&redis.Options{
		Addr: cfg.RedisURL,
	})
	semPool := semaphore.NewRedisPool(redisClient)

	// HTTP handlers
	semHandler := semaphore.NewHandler(semPool)

	// API routes
	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	// Semaphore routes
	http.HandleFunc("/semaphore/acquire", semHandler.Acquire)
	http.HandleFunc("/semaphore/release", semHandler.Release)
	http.HandleFunc("/semaphore/health", semHandler.Health)

	srv := &http.Server{
		Addr:         ":" + cfg.APIPort,
		Handler:      nil,
		IdleTimeout:  serverIdleTimeout,
		ReadTimeout:  serverReadTimeout,
		WriteTimeout: serverWriteTimeout,
	}

	// Get context
	ctx, cancel := context.WithCancel(context.Background())

	// Register this API server with the balancer
	go registerUpstream(ctx)

	// Start API server
	go func() {
		logger.Info("🐎 API server started", "port", cfg.APIPort)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Error("💥 Server crashed", "error", err)
			os.Exit(1)
		}
	}()

	// Wait for shutdown signal
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logger.Info("🛑 Shutting down...")

	// Cancel context to stop registration heartbeat
	cancel()

	// Graceful shutdown
	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), shutdownTimeout)
	defer shutdownCancel()

	// Stop server
	if err := srv.Shutdown(shutdownCtx); err != nil {
		logger.Error("⚠️ Shutdown error", "error", err)
	}

	logger.Info("🔴 Stopped")
}
