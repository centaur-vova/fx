package main

import (
	"context"
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/protocol"
	"fast-atomic-flow/go/internal/semaphore"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/joho/godotenv"
)

var (
	cfg *protocol.AppConfig
)

func main() {
	// ==== LOAD .env ====
	if err := godotenv.Load("../.env"); err != nil {
		slog.Info("No .env file found, using system env")
	}

	// ==== LOAD CONFIG ====
	cfg = protocol.LoadConfig()
	cfg.Validate()

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
	http.HandleFunc("/semaphore/acquire", semHandler.AuthMiddleware(cfg.APIToken, semHandler.Acquire))
	http.HandleFunc("/semaphore/release", semHandler.AuthMiddleware(cfg.APIToken, semHandler.Release))
	http.HandleFunc("/semaphore/health", semHandler.Health)

	// ==== INIT WEBSOCKET SERVER ====
	srv := &http.Server{
		Addr:        ":" + cfg.APIPort,
		Handler:     nil,
		IdleTimeout: 60 * time.Second, // keep idle connections alive long enough
	}

	// ==== RUN API SERVER IN GOROUTINE ====
	go func() {
		slog.Info("API server started", "port", cfg.APIPort)
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

	slog.Info("API Server Stopped")
}
