package balancer

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/cb"
	"fast-atomic-flow/go/internal/logger"
	"io"
	"net/http"
	"sync/atomic"
	"time"
)

const (
	contentTypeJSON = "application/json"
)

var (
	totalRequests atomic.Uint64
	totalErrors   atomic.Uint64
	startTime     = time.Now()
)

// ========== TYPES ==========

type InstanceHealth struct {
	Hash     string `json:"hash"`
	Requests uint64 `json:"requests"`
	Errors   uint64 `json:"errors"`
	Alive    bool   `json:"alive"`
	CBState  string `json:"cb_state"`
}

// HealthResponse is the response for the health check endpoint
type HealthResponse struct {
	Up            uint64           `json:"up"`
	Down          uint64           `json:"down"`
	TotalRequests uint64           `json:"total_requests"`
	TotalErrors   uint64           `json:"total_errors"`
	UptimeSeconds uint64           `json:"uptime_seconds"`
	Instances     []InstanceHealth `json:"instances"`
}

// ========== HTTP HANDLERS ==========

// Handles POST requests to register a new API instance
func RegisterHandler(u *Upstream) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		body, err := io.ReadAll(r.Body)
		if err != nil {
			http.Error(w, "Failed to read body", http.StatusBadRequest)
			return
		}
		defer r.Body.Close()

		targetURL := string(body)
		u.RegisterInstance(targetURL)
		w.WriteHeader(http.StatusOK)
	}
}

// ReviveHandler returns an HTTP handler that revives a forcefully unalived instance.
//
// Request body: {"hash": "35192206"}
func ReviveHandler(u *Upstream) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		var req struct {
			Hash string `json:"hash"`
		}

		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusBadRequest)
			json.NewEncoder(w).Encode(map[string]string{"error": "invalid request body"})
			return
		}

		if req.Hash == "" {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusBadRequest)
			json.NewEncoder(w).Encode(map[string]string{"error": "hash parameter required"})
			return
		}

		if !u.ReviveInstance(req.Hash) {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusNotFound)
			json.NewEncoder(w).Encode(map[string]string{"error": "instance not found"})
			return
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]string{
			"status":  "ok",
			"message": "instance revived for hash " + req.Hash,
		})
	}
}

// ForceUnaliveHandler returns an HTTP handler that forcibly marks an API instance as dead
// for a specific API instance identified by its hash from the request body.
//
// The instance will be marked as unavailable and no new requests will be routed to it.
// It will automatically become alive again on the next registration heartbeat (20 seconds).
//
// Returns 200 OK on success, or appropriate error status if the instance is not found
// or the operation fails.
func ForceUnaliveHandler(u *Upstream) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		var req struct {
			Hash string `json:"hash"`
		}

		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusBadRequest)
			json.NewEncoder(w).Encode(map[string]string{"error": "invalid request body"})
			return
		}

		if req.Hash == "" {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusBadRequest)
			json.NewEncoder(w).Encode(map[string]string{"error": "hash parameter required"})
			return
		}

		// Find instance by hash
		var peer *ApiInstance
		u.mu.RLock()
		for _, inst := range u.ApiInstances {
			if inst.Hash == req.Hash {
				peer = inst
				break
			}
		}
		u.mu.RUnlock()

		if peer == nil {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusNotFound)
			json.NewEncoder(w).Encode(map[string]string{"error": "instance not found"})
			return
		}

		peer.SetUnalive(true)

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]string{
			"status":  "ok",
			"message": "instance marked as dead for hash " + req.Hash,
		})
	}
}

// Returns the current health status of all instances
func HealthHandler(u *Upstream) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", contentTypeJSON)
		w.WriteHeader(http.StatusOK)

		// TODO - add caching
		apiInstances := u.getInstancesCopy()
		instances := make([]InstanceHealth, len(apiInstances))
		for idx, i := range apiInstances {
			instances[idx] = InstanceHealth{
				// simply copy data from ApiInstance to InstanceHealth
				Hash:     i.Hash,
				Requests: i.Requests.Load(),
				Errors:   i.Errors.Load(),
				Alive:    i.IsAlive(),
				CBState:  cbStateString(i.CB.GetState()),
			}
		}

		up, down := u.Counts()
		resp := HealthResponse{
			Up:            up,
			Down:          down,
			TotalRequests: totalRequests.Load(),
			TotalErrors:   totalErrors.Load(),
			UptimeSeconds: uint64(time.Since(startTime).Seconds()),
			Instances:     instances,
		}

		json.NewEncoder(w).Encode(resp)
	}
}

// Proxy all other requests
func ProxyHandler(u *Upstream) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		// Stats
		totalRequests.Add(1)

		peer := u.NextInstance()
		if peer == nil {
			http.Error(w, "API Instances gone fishing (KBL v2.0 Rule)", http.StatusServiceUnavailable)
			totalErrors.Add(1)
			return
		}

		peer.Requests.Add(1)

		// CB check
		allowed, isHalfOpen := peer.CB.CanRequest()
		if !allowed {
			http.Error(w, "Circuit Breaker: Instance is cooling down", http.StatusServiceUnavailable)
			totalErrors.Add(1)
			peer.Errors.Add(1)
			return
		}
		if isHalfOpen {
			logger.Info("🟡 Circuit Breaker HALF-OPEN - probing instance", "instance", peer.URL.Host)
		}

		// Catch network errors during proxying
		peer.Proxy.ErrorHandler = func(rw http.ResponseWriter, req *http.Request, err error) {
			logger.Error("💥 Proxy error inside handler", "host", peer.URL.Host, "error", err)

			// Unalive the peer if CB just became opened
			if peer.CB.RecordFailure() {
				peer.SetUnalive(false)
				logger.Warn("🔴 Circuit Breaker OPENED - instance isolated", "instance", peer.URL.Host)
			}
			rw.WriteHeader(http.StatusBadGateway)
		}

		// Try proxying the request
		peer.Proxy.ServeHTTP(w, r)

		// Check if the peer instance has just recovered
		if peer.CB.RecordSuccess() {
			logger.Info("🟢 Circuit Breaker CLOSED - instance fully recovered", "instance", peer.URL.Host)
		}

		// Mark the peer as alive
		if peer.CB.GetState() == cb.StateClosed && !peer.IsAlive() && !peer.IsForcedUnalived() {
			peer.SetAlive()
		}
	}
}

func cbStateString(state uint32) string {
	switch state {
	case cb.StateClosed:
		return "closed"
	case cb.StateOpen:
		return "open"
	case cb.StateHalfOpen:
		return "half_open"
	default:
		return "unknown"
	}
}
