package main

import (
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/protocol"
	"log"
	"log/slog"
	"net/http"
	"net/http/httputil"
	"net/url"
	"sync"
	"sync/atomic"
	"time"

	"github.com/joho/godotenv"
)

var (
	cfg *protocol.AppConfig
)

type ApiInstance struct {
	URL   *url.URL
	Alive bool
	Proxy *httputil.ReverseProxy
	mu    sync.RWMutex
}

func (h *ApiInstance) SetAlive(alive bool) {
	h.mu.Lock()
	h.Alive = alive
	h.mu.Unlock()
}

func (h *ApiInstance) IsAlive() bool {
	h.mu.RLock()
	alive := h.Alive
	h.mu.RUnlock()
	return alive
}

type Upstream struct {
	ApiInstances []*ApiInstance
	current      uint64
}

func (u *Upstream) AddInstance(targetURL string) {
	origin, err := url.Parse(targetURL)
	if err != nil {
		log.Fatalf("Invalid URL: %v", err)
	}

	proxy := httputil.NewSingleHostReverseProxy(origin)

	proxy.ErrorHandler = func(w http.ResponseWriter, r *http.Request, e error) {
		log.Printf("[%s] Proxy error: %v", origin.Host, e)
		w.WriteHeader(http.StatusBadGateway)
	}

	u.ApiInstances = append(u.ApiInstances, &ApiInstance{
		URL:   origin,
		Alive: true,
		Proxy: proxy,
	})
}

// Round-Robin
func (u *Upstream) NextInstance() *ApiInstance {
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
		for _, h := range u.ApiInstances {
			// Check health
			resp, err := client.Get(h.URL.String() + "/health")
			if err != nil || resp.StatusCode != http.StatusOK {
				if h.IsAlive() {
					h.SetAlive(false)
					log.Printf("🛑 API Instance %s has been unalived. Sending to medically induced coma", h.URL.Host)
				}
			} else {
				if !h.IsAlive() {
					h.SetAlive(true)
					log.Printf("🐎 API Instance %s has been reanimated. Waking up from coma!", h.URL.Host)
				}
				resp.Body.Close()
			}
		}
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
	logger.Init(cfg.LogLevel)

	upstream := &Upstream{}

	// Add instances
	for _, instance := range cfg.BalancerApiURLs {
		upstream.AddInstance(instance)
	}

	// Run periodic Health Check in goroutine
	go upstream.HealthCheck()

	server := http.Server{
		Addr: ":" + cfg.BalancerPort,
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			peer := upstream.NextInstance()
			if peer != nil {
				peer.Proxy.ServeHTTP(w, r)
				return
			}
			http.Error(w, "API Instances gone fishing (KBL v2.0 Rule)", http.StatusServiceUnavailable)
		}),
	}

	log.Println("Balancer service started :" + cfg.BalancerPort)
	if err := server.ListenAndServe(); err != nil {
		log.Fatalf("Balancer crashed: %v", err)
	}
}
