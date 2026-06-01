package semaphore

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/logger"
	"fmt"
	"net/http"
	"time"
)

const defaultMC = 1
const maxPermitTTLSec = 60
const maxMC = 255

// Requests
type AcquireRequest struct {
	MaxConcurrent   int `json:"max_concurrent" minimum:"1" maximum:"255" example:"5"`
	LockWaitTimeout int `json:"lock_wait_timeout" minimum:"0" example:"1"`
	PermitTTL       int `json:"permit_ttl" minimum:"1" maximum:"60" example:"2"`
}

type ReleaseRequest struct {
	UID SlotUID `json:"uid" example:"5:3"`
}

// Handler
type Handler struct {
	pool Pool
}

func NewHandler(pool Pool) *Handler {
	return &Handler{pool: pool}
}

// Acquire acquires a slot in the specified semaphore.
// @Summary      Acquire semaphore slot
// @Description  Acquires a slot in the distributed semaphore. Returns a unique slot UID on success.
// @Security     ApiKeyAuth
// @Accept       json
// @Produce      json
// @Param        request body AcquireRequest true "Semaphore acquire request"
// @Success      200 {object} map[string]SlotUID
// @Failure      400 {string} string "invalid JSON body"
// @Failure      409 {string} string "no available slots"
// @Router       /semaphore/acquire [post]
func (h *Handler) Acquire(w http.ResponseWriter, r *http.Request) {
	var req AcquireRequest

	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "invalid JSON body: "+err.Error(), http.StatusBadRequest)
		return
	}

	if req.MaxConcurrent <= 0 || req.MaxConcurrent > maxMC {
		http.Error(w, fmt.Sprintf("max_concurrent must be between 1 and %d", maxMC), http.StatusBadRequest)
		return
	}

	if req.LockWaitTimeout < 0 {
		http.Error(w, "lock_wait_timeout must be non-negative", http.StatusBadRequest)
		return
	}

	if req.PermitTTL <= 0 || req.PermitTTL > maxPermitTTLSec {
		http.Error(w, fmt.Sprintf("permit_ttl must be between 1 and %d", maxPermitTTLSec), http.StatusBadRequest)
		return
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
	json.NewEncoder(w).Encode(map[string]SlotUID{"uid": uid})
}

// Release releases a previously acquired semaphore slot.
// @Summary      Release semaphore slot
// @Description  Releases a slot identified by its UID. Idempotent — releasing an already released slot is a no-op.
// @Security     ApiKeyAuth
// @Accept       json
// @Produce      plain
// @Param        request body ReleaseRequest true "Slot UID to release"
// @Success      200 "Slot released (or was already free)"
// @Failure      400 {string} string "invalid request"
// @Failure      500 {string} string "redis connection error"
// @Router       /semaphore/release [post]
func (h *Handler) Release(w http.ResponseWriter, r *http.Request) {
	var req ReleaseRequest

	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "invalid JSON body: "+err.Error(), http.StatusBadRequest)
		return
	}

	if err := h.pool.Release(req.UID); err != nil {
		logger.Error("Failed to release semaphore", "error", err)
		http.Error(w, "release failed", http.StatusInternalServerError)
		return
	}

	logger.Debug("Semaphore released", "uid", req.UID)
	w.WriteHeader(http.StatusOK)
}

// Health returns 200 if semaphore is up
// @Summary      Semaphore health check
// @Description  Returns 200 OK
// @Produce      json
// @Success      200
// @Router       /semaphore/health [get]
func (h *Handler) Health(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
}
