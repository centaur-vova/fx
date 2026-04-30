package semaphore

import (
	"encoding/json"
	"log/slog"
	"net/http"
	"time"
)

const defaultMC = 1
const defaultLockWaitTimeoutSec = 5
const maxPermitTTLSec = 60
const maxMC = 255

type Handler struct {
	pool *Pool
}

func NewHandler(pool *Pool) *Handler {
	return &Handler{pool: pool}
}

func (h *Handler) Acquire(w http.ResponseWriter, r *http.Request) {
	var req struct {
		MaxConcurrent   int `json:"max_concurrent"`
		LockWaitTimeout int `json:"lock_wait_timeout"`
		PermitTTL       int `json:"permit_ttl"`
	}

	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "invalid JSON body: "+err.Error(), http.StatusBadRequest)
		return
	}

	if req.MaxConcurrent <= 0 || req.MaxConcurrent > maxMC {
		req.MaxConcurrent = defaultMC
	}
	if req.LockWaitTimeout <= 0 {
		req.LockWaitTimeout = defaultLockWaitTimeoutSec
	}
	if req.PermitTTL <= 0 || req.PermitTTL > maxPermitTTLSec {
		req.PermitTTL = maxPermitTTLSec
	}

	slog.Debug(
		"acquire request",
		"max_concurrent",
		req.MaxConcurrent,
		"lock_wait_timeout",
		req.LockWaitTimeout,
		"permit_ttl",
		req.PermitTTL,
	)

	uid, err := h.pool.Acquire(
		r.Context(),
		req.MaxConcurrent,
		time.Duration(req.LockWaitTimeout)*time.Second,
		time.Duration(req.PermitTTL)*time.Second,
	)

	if err != nil {
		http.Error(w, err.Error(), http.StatusConflict)
		return
	}

	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]uint64{"uid": uid})
}

func (h *Handler) Release(w http.ResponseWriter, r *http.Request) {
	var req struct {
		UID uint64 `json:"uid"`
	}

	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "invalid JSON body: "+err.Error(), http.StatusBadRequest)
		return
	}

	slog.Debug("release request", "uid", req.UID)

	h.pool.Release(req.UID)
	w.WriteHeader(http.StatusOK)
}

// AuthMiddleware wraps a handler to check for a valid Bearer token
func (h *Handler) AuthMiddleware(apiToken string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer "+apiToken {
			slog.Warn("forbidden: invalid or missing auth token",
				"remote_addr", r.RemoteAddr,
				"path", r.URL.Path,
			)
			http.Error(w, "Forbidden", http.StatusForbidden)
			return
		}
		next.ServeHTTP(w, r)
	}
}
