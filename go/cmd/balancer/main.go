package main

import (
	"context"
	"encoding/json"
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

// ========== CONSTANTS ==========
const (
	// Health check settings
	healthCheckTimeout  = 2 * time.Second
	healthCheckInterval = 5 * time.Second
	healthCheckPath     = "/health"

	// Instance lifecycle
	instanceTTL     = 30 * time.Second
	cleanupInterval = 30 * time.Second

	// Server
	shutdownTimeout = 5 * time.Second

	// HTTP
	contentTypeJSON = "application/json"

	// Balancer
	defaultPort = "8080"
)

// ========== GLOBALS ==========
var (
	cfg      *protocol.BalancerConfig
	upstream *Upstream
)

// ========== TYPES ==========

// ApiInstance represents a single upstream API instance
type ApiInstance struct {
	URL       *url.URL
	Alive     atomic.Bool
	ExpiresAt atomic.Int64
	Proxy     *httputil.ReverseProxy
	mu        sync.RWMutex
}

// Upstream manages a pool of API instances with round-robin load balancing
type Upstream struct {
	mu           sync.RWMutex
	ApiInstances []*ApiInstance
	current      uint64
	checkWg      sync.WaitGroup
}

// HealthResponse is the response for the health check endpoint
type HealthResponse struct {
	Up   uint64 `json:"up"`
	Down uint64 `json:"down"`
}

// ========== API INSTANCE METHODS ==========

// SetAlive sets the alive status of the instance
func (h *ApiInstance) SetAlive(alive bool) {
	h.Alive.Store(alive)
}

// IsAlive returns whether the instance is considered alive
func (h *ApiInstance) IsAlive() bool {
	return h.Alive.Load()
}

// Touch updates the expiration timestamp of the instance
// Extends the instance's life by instanceTTL
func (h *ApiInstance) Touch() {
	h.ExpiresAt.Store(time.Now().Add(instanceTTL).UnixNano())
}

// ========== UPSTREAM METHODS ==========

// RegisterInstance adds or updates an API instance in the pool
func (u *Upstream) RegisterInstance(targetURL string) {
	origin, err := url.Parse(targetURL)
	if err != nil {
		logger.Emergency("💥 Invalid URL", "error", err)
		os.Exit(1)
	}

	u.mu.Lock()
	defer u.mu.Unlock()

	// Check if instance already exists
	for _, inst := range u.ApiInstances {
		if inst.URL.String() == targetURL {
			// Update existing instance
			inst.Touch()
			if !inst.IsAlive() {
				inst.SetAlive(true)
				logger.Info("🐎 Instance re-registered and revived", "instance", targetURL)
			} else {
				logger.Debug("💓 Heartbeat received", "instance", targetURL)
			}
			return
		}
	}

	// New instance - create and add
	proxy := httputil.NewSingleHostReverseProxy(origin)
	proxy.ErrorHandler = func(w http.ResponseWriter, r *http.Request, e error) {
		logger.Error("💥 Proxy error", "host", origin.Host, "error", e)
		w.WriteHeader(http.StatusBadGateway)
	}

	instance := &ApiInstance{
		URL:   origin,
		Proxy: proxy,
	}
	instance.SetAlive(true)
	instance.Touch()

	u.ApiInstances = append(u.ApiInstances, instance)
	logger.Info("➕ New API instance registered", "instance", targetURL)
}

// NextInstance returns the next alive instance using round-robin selection
func (u *Upstream) NextInstance() *ApiInstance {
	u.mu.RLock()
	defer u.mu.RUnlock()

	n := len(u.ApiInstances)
	if n == 0 {
		return nil
	}

	// Find first alive instance
	for range n {
		idx := atomic.AddUint64(&u.current, 1) % uint64(n)
		if u.ApiInstances[idx].IsAlive() {
			return u.ApiInstances[idx]
		}
	}
	return nil
}

// HealthCheck runs periodic health checks on all instances
func (u *Upstream) HealthCheck(ctx context.Context) {
	client := &http.Client{Timeout: healthCheckTimeout}
	ticker := time.NewTicker(healthCheckInterval)
	defer ticker.Stop()

	// Run check immediately on start
	u.checkAllInstances(client)

	for {
		select {
		case <-ctx.Done():
			logger.Info("🛑 Health Check stopped, waiting for active checks...")
			u.checkWg.Wait()
			logger.Info("🛑 All Health Check completed")
			return
		case <-ticker.C:
			u.checkAllInstances(client)
		}
	}
}

// checkAllInstances triggers health checks for all instances concurrently
func (u *Upstream) checkAllInstances(client *http.Client) {
	instances := u.getInstancesCopy()

	for _, i := range instances {
		u.checkWg.Add(1)
		go func(inst *ApiInstance) {
			defer u.checkWg.Done()
			u.checkInstance(client, inst)
		}(i)
	}
}

// checkInstance performs a single health check on an instance
func (u *Upstream) checkInstance(client *http.Client, inst *ApiInstance) {
	resp, err := client.Get(inst.URL.String() + healthCheckPath)
	if resp != nil {
		defer resp.Body.Close()
	}

	isHealthy := err == nil && resp != nil && resp.StatusCode == http.StatusOK

	if isHealthy {
		inst.Touch()
		if !inst.IsAlive() {
			inst.SetAlive(true)
			logger.Info("🐎 Reanimated", "instance", inst.URL.Host)
		}
	} else if inst.IsAlive() {
		inst.SetAlive(false)
		logger.Warn("🛑 Expired", "instance", inst.URL.Host)
	}
}

// CleanupDeadInstances periodically removes expired instances
func (u *Upstream) CleanupDeadInstances(ctx context.Context) {
	ticker := time.NewTicker(cleanupInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			u.removeDeadInstances()
		}
	}
}

// removeDeadInstances removes instances that have exceeded their TTL
func (u *Upstream) removeDeadInstances() {
	instances := u.getInstancesCopy()

	now := time.Now().UnixNano()
	alive := make([]*ApiInstance, 0, len(instances))
	for _, inst := range instances {
		if inst.ExpiresAt.Load() > now {
			alive = append(alive, inst)
		} else {
			logger.Warn("🗑️ Removing expired instance", "instance", inst.URL.Host)
		}
	}

	u.mu.Lock()
	u.ApiInstances = alive
	u.mu.Unlock()
}

// getInstancesCopy returns a thread-safe copy of the instances slice
func (u *Upstream) getInstancesCopy() []*ApiInstance {
	u.mu.RLock()
	defer u.mu.RUnlock()

	instances := make([]*ApiInstance, len(u.ApiInstances))
	copy(instances, u.ApiInstances)
	return instances
}

// ========== HTTP HANDLERS ==========

// handleRegister handles POST requests to register a new API instance
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

// handleHealth returns the current health status of all instances
func handleHealth(w http.ResponseWriter, r *http.Request) {
	upstream.mu.RLock()
	defer upstream.mu.RUnlock()

	var up, down uint64

	for _, i := range upstream.ApiInstances {
		if i.Alive.Load() {
			up++
		} else {
			down++
		}
	}

	w.Header().Set("Content-Type", contentTypeJSON)
	w.WriteHeader(http.StatusOK)

	resp := HealthResponse{
		Up:   up,
		Down: down,
	}

	json.NewEncoder(w).Encode(resp)
}

// ========== MAIN ==========

func main() {
	// Load .env file
	godotenv.Load("../.env")

	// Load configuration
	cfg = protocol.LoadBalancerConfig()

	// Initialize logger
	logger.Init(cfg.LogLevel)

	upstream = &Upstream{}

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Start background goroutines
	go upstream.HealthCheck(ctx)
	go upstream.CleanupDeadInstances(ctx)

	// Setup router
	mux := http.NewServeMux()

	mux.HandleFunc("GET /health", handleHealth)
	mux.HandleFunc("POST /register", middleware.AuthMiddleware(cfg.APIToken, handleRegister))

	// Proxy all other requests
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		peer := upstream.NextInstance()
		if peer != nil {
			peer.Proxy.ServeHTTP(w, r)
			return
		}
		http.Error(w, "API Instances gone fishing (KBL v2.0 Rule)", http.StatusServiceUnavailable)
	})

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

	logger.Info("🔴 Stopped")
}
