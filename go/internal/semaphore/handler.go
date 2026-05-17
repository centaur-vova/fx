package semaphore

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/logger"
	"net/http"
	"runtime"
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

	uid, err := h.pool.Acquire(
		r.Context(),
		req.MaxConcurrent,
		time.Duration(req.LockWaitTimeout)*time.Second,
		time.Duration(req.PermitTTL)*time.Second,
	)

	if err != nil {
		logger.Debug("Semaphore acquire failed", "error", err)
		http.Error(w, err.Error(), http.StatusConflict)
		return
	}

	logger.Debug("Semaphore acquired", "uid", uid)

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

	h.pool.Release(req.UID)
	logger.Debug("Semaphore released", "uid", req.UID)
	w.WriteHeader(http.StatusOK)
}

func (h *Handler) Health(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)

	permitCount := h.pool.PermitCount()
	numGoroutine := runtime.NumGoroutine()

	json.NewEncoder(w).Encode(map[string]int{"permits": permitCount, "numGoroutine": numGoroutine})
}
