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

// HealthResponse is the response for the health check endpoint
type HealthResponse struct {
	Up            uint64 `json:"up"`
	Down          uint64 `json:"down"`
	TotalRequests uint64 `json:"total_requests"`
	TotalErrors   uint64 `json:"total_errors"`
	UptimeSeconds uint64 `json:"uptime_seconds"`
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

// Returns the current health status of all instances
func HealthHandler(u *Upstream) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", contentTypeJSON)
		w.WriteHeader(http.StatusOK)

		up, down := u.Counts()

		resp := HealthResponse{
			Up:            up,
			Down:          down,
			TotalRequests: totalRequests.Load(),
			TotalErrors:   totalErrors.Load(),
			UptimeSeconds: uint64(time.Since(startTime).Seconds()),
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

		// CB check
		allowed, isHalfOpen := peer.CB.CanRequest()
		if !allowed {
			http.Error(w, "Circuit Breaker: Instance is cooling down", http.StatusServiceUnavailable)
			totalErrors.Add(1)
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
				peer.SetAlive(false)
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
		if peer.CB.GetState() == cb.StateClosed && !peer.IsAlive() {
			peer.SetAlive(true)
		}
	}
}
