package semaphore

import (
	"bytes"
	"encoding/json"
	"fast-atomic-flow/go/internal/clock"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/alicebob/miniredis/v2"
	"github.com/redis/go-redis/v9"
)

// newTestHandler creates a Handler backed by miniredis (in-memory Redis)
func newTestHandler(t *testing.T) *Handler {
	t.Helper()
	mr := miniredis.RunT(t)
	client := redis.NewClient(&redis.Options{Addr: mr.Addr()})
	return NewHandler(NewRedisPool(client, clock.RealClock{}))
}

func TestHandler_Acquire_Success(t *testing.T) {
	h := newTestHandler(t)
	body := []byte(`{"max_concurrent": 2, "lock_wait_timeout": 1, "permit_ttl": 10}`)
	req := httptest.NewRequest("POST", "/acquire", bytes.NewReader(body))
	rec := httptest.NewRecorder()

	h.Acquire(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	var resp map[string]string
	if err := json.NewDecoder(rec.Body).Decode(&resp); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}
	if resp["uid"] == "" {
		t.Error("expected non-empty uid")
	}
}

func TestHandler_Acquire_InvalidJSON(t *testing.T) {
	h := newTestHandler(t)
	req := httptest.NewRequest("POST", "/acquire", bytes.NewReader([]byte(`not json`)))
	rec := httptest.NewRecorder()

	h.Acquire(rec, req)

	if rec.Code != http.StatusBadRequest {
		t.Errorf("expected 400, got %d", rec.Code)
	}
}

func TestHandler_Release(t *testing.T) {
	h := newTestHandler(t)

	// Acquire
	req1 := httptest.NewRequest("POST", "/acquire", bytes.NewReader([]byte(`{"max_concurrent":1,"lock_wait_timeout":1,"permit_ttl":10}`)))
	rec1 := httptest.NewRecorder()
	h.Acquire(rec1, req1)

	var resp map[string]string
	if err := json.NewDecoder(rec1.Body).Decode(&resp); err != nil {
		t.Fatalf("failed to decode response: %v", err)
	}
	uid := resp["uid"]

	// Release
	body, _ := json.Marshal(map[string]string{"uid": uid})
	req2 := httptest.NewRequest("POST", "/release", bytes.NewReader(body))
	rec2 := httptest.NewRecorder()
	h.Release(rec2, req2)

	if rec2.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", rec2.Code)
	}
}

func TestHandler_Health(t *testing.T) {
	h := newTestHandler(t)
	req := httptest.NewRequest("GET", "/health", nil)
	rec := httptest.NewRecorder()

	h.Health(rec, req)

	if rec.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", rec.Code)
	}
}
