package main

import (
	"context"
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/middleware"
	"fast-atomic-flow/go/internal/protocol"
	"io"
	"net/http"
	"net/http/httputil"
	"net/url"
	"os"
	"os/signal"
	"sync"
	"sync/atomic"
	"syscall"
	"time"

	"github.com/joho/godotenv"
)

var (
	cfg      *protocol.BalancerConfig
	upstream *Upstream
)

type ApiInstance struct {
	URL   *url.URL
	Alive atomic.Bool
	Proxy *httputil.ReverseProxy
	mu    sync.RWMutex
}

type Upstream struct {
	mu           sync.RWMutex
	ApiInstances []*ApiInstance
	current      uint64
}

func main() {
	// ==== LOAD .env ====
	godotenv.Load("../.env")

	// ==== LOAD CONFIG ====
	cfg = protocol.LoadBalancerConfig()

	// === LOGGER ===
	logger.Init(cfg.LogLevel)

	upstream = &Upstream{}

	// Run periodic Health Check in goroutine
	go upstream.HealthCheck()

	// Create router
	mux := http.NewServeMux()

	// "Health" route
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	// /register
	mux.HandleFunc("POST /register", middleware.AuthMiddleware(cfg.APIToken, handleRegister))

	// Proxy other requests
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		peer := upstream.NextInstance()
		if peer != nil {
			peer.Proxy.ServeHTTP(w, r)
			return
		}
		http.Error(w, "API Instances gone fishing (KBL v2.0 Rule)", http.StatusServiceUnavailable)
	})

	server := http.Server{
		Addr:    ":" + cfg.BalancerPort,
		Handler: mux,
	}

	go func() {
		logger.Info("🐎 Balancer service started", "addr", ":"+cfg.BalancerPort)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Emergency("💥 Balancer crashed", "error", err)
			os.Exit(1)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logger.Info("🛑 Shutting down...")
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := server.Shutdown(ctx); err != nil {
		logger.Error("💥 Shutdown error", "error", err)
	}

	logger.Info("🔴 Stopped")
}

func (h *ApiInstance) SetAlive(alive bool) {
	h.Alive.Store(alive)
}

func (h *ApiInstance) IsAlive() bool {
	return h.Alive.Load()
}

func (u *Upstream) RegisterInstance(targetURL string) {
	origin, err := url.Parse(targetURL)
	if err != nil {
		logger.Emergency("💥 Invalid URL", "error", err)
		os.Exit(1)
	}

	proxy := httputil.NewSingleHostReverseProxy(origin)
	proxy.ErrorHandler = func(w http.ResponseWriter, r *http.Request, e error) {
		logger.Error("💥 Proxy error", "host", origin.Host, "error", e)
		w.WriteHeader(http.StatusBadGateway)
	}

	instance := &ApiInstance{
		URL:   origin,
		Proxy: proxy,
	}
	// Mark as alive
	instance.SetAlive(true)

	u.mu.Lock()
	u.ApiInstances = append(u.ApiInstances, instance)
	u.mu.Unlock()
}

// Round-Robin
func (u *Upstream) NextInstance() *ApiInstance {
	u.mu.RLock()
	defer u.mu.RUnlock()

	n := len(u.ApiInstances)
	if n == 0 {
		return nil
	}

	// Find first "alive" instance
	for range n {
		idx := atomic.AddUint64(&u.current, 1) % uint64(n)
		if u.ApiInstances[idx].IsAlive() {
			return u.ApiInstances[idx]
		}
	}
	return nil
}

func (u *Upstream) HealthCheck() {
	client := http.Client{Timeout: 2 * time.Second}
	for {
		time.Sleep(5 * time.Second) // Every 5 seconds

		if len(u.ApiInstances) == 0 {
			continue
		}

		// Make a copy of instances
		u.mu.RLock()
		instances := make([]*ApiInstance, len(u.ApiInstances))
		copy(instances, u.ApiInstances)
		u.mu.RUnlock()

		for _, h := range instances {
			// Check health
			resp, err := client.Get(h.URL.String() + "/health")
			if err != nil || resp.StatusCode != http.StatusOK {
				if h.IsAlive() {
					h.SetAlive(false)
					logger.Warn("🛑 API Instance marked as dead", "instance", h.URL.Host)
				}
			} else {
				if !h.IsAlive() {
					h.SetAlive(true)
					logger.Info("🐎 API Instance reanimated", "instance", h.URL.Host)
				}
				resp.Body.Close()
			}
		}
	}
}

func handleRegister(w http.ResponseWriter, r *http.Request) {
	body, err := io.ReadAll(r.Body)
	if err != nil {
		http.Error(w, "Failed to read body", http.StatusBadRequest)
		return
	}
	defer r.Body.Close()

	targetURL := string(body)
	upstream.RegisterInstance(targetURL)
	w.WriteHeader(http.StatusOK)
}
