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
)

var (
	cfg *protocol.APIConfig
)

func main() {
	// ==== LOAD .env ====
	godotenv.Load("../.env")

	// ==== LOAD CONFIG ====
	cfg = protocol.LoadAPIConfig()

	// === LOGGER ===
	logger.Init(cfg.LogLevel)

	// === HTTP HANDLERS ===
	semPool := semaphore.NewPool()
	semHandler := semaphore.NewHandler(semPool)

	// API
	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK) // kon-not-dead
	})

	// Semaphore
	http.HandleFunc("/semaphore/acquire", semHandler.Acquire)
	http.HandleFunc("/semaphore/release", semHandler.Release)
	http.HandleFunc("/semaphore/health", semHandler.Health)

	// ==== INIT WEBSOCKET SERVER ====
	srv := &http.Server{
		Addr:        ":" + cfg.APIPort,
		Handler:     nil,
		IdleTimeout: 60 * time.Second, // keep idle connections alive long enough
	}

	// ==== REGISTER API SERVER IN BALANCER ====
	go registerUpstream()

	// ==== RUN API SERVER IN GOROUTINE ====
	go func() {
		logger.Info("🐎 API server started", "port", cfg.APIPort)
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
		logger.Error("⚠️ Shutdown error", "error", err)
	}

	logger.Info("🔴 Stopped")
}

func registerUpstream() {
	client := http.Client{Timeout: 2 * time.Second}
	registerURL := cfg.BalancerURL + "/register"

	hostname, _ := os.Hostname()
	targetURL := fmt.Sprintf("http://%s:%s", hostname, cfg.APIPort)

	for {
		req, err := http.NewRequest("POST", registerURL, strings.NewReader(targetURL))
		if err == nil {
			req.Header.Set("Authorization", "Bearer "+cfg.APIToken)
			resp, err := client.Do(req)

			if err == nil && resp.StatusCode == http.StatusOK {
				resp.Body.Close()
				logger.Info("🚀 Registration successful!", "instance", targetURL)
				return
			}
			if resp != nil {
				resp.Body.Close()
			}
		}

		logger.Warn("⏳ Balancer unavailable. Retrying registration in 3 seconds...")
		time.Sleep(3 * time.Second) // try again in a few
	}
}
