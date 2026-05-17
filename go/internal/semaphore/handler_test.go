package semaphore

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func newTestHandler() *Handler {
	return NewHandler(NewPool())
}

func TestHandler_Acquire_Success(t *testing.T) {
	h := newTestHandler()
	body := []byte(`{"max_concurrent": 2, "lock_wait_timeout": 1, "permit_ttl": 10}`)
	req := httptest.NewRequest("POST", "/acquire", bytes.NewReader(body))
	rec := httptest.NewRecorder()

	h.Acquire(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}

	var resp map[string]uint64
	json.NewDecoder(rec.Body).Decode(&resp)
	if resp["uid"] == 0 {
		t.Error("expected non-zero uid")
	}
}

func TestHandler_Acquire_InvalidJSON(t *testing.T) {
	h := newTestHandler()
	req := httptest.NewRequest("POST", "/acquire", bytes.NewReader([]byte(`not json`)))
	rec := httptest.NewRecorder()

	h.Acquire(rec, req)

	if rec.Code != http.StatusBadRequest {
		t.Errorf("expected 400, got %d", rec.Code)
	}
}

func TestHandler_Acquire_DefaultValues(t *testing.T) {
	h := newTestHandler()

	// Send garbage values, handler should fall back to defaults
	body := []byte(`{"max_concurrent": -5, "lock_wait_timeout": 0, "permit_ttl": 999}`)
	req := httptest.NewRequest("POST", "/acquire", bytes.NewReader(body))
	rec := httptest.NewRecorder()

	h.Acquire(rec, req)

	// Should succeed with defaults, not error
	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200 with defaults applied, got %d: %s", rec.Code, rec.Body.String())
	}
}

func TestHandler_Release(t *testing.T) {
	h := newTestHandler()

	// First acquire
	req1 := httptest.NewRequest("POST", "/acquire", bytes.NewReader([]byte(`{"max_concurrent":1,"lock_wait_timeout":1,"permit_ttl":10}`)))
	rec1 := httptest.NewRecorder()
	h.Acquire(rec1, req1)

	var resp map[string]uint64
	json.NewDecoder(rec1.Body).Decode(&resp)
	uid := resp["uid"]

	// Then release
	body, _ := json.Marshal(map[string]uint64{"uid": uid})
	req2 := httptest.NewRequest("POST", "/release", bytes.NewReader(body))
	rec2 := httptest.NewRecorder()
	h.Release(rec2, req2)

	if rec2.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", rec2.Code)
	}
}

func TestHandler_Health(t *testing.T) {
	h := newTestHandler()
	req := httptest.NewRequest("GET", "/health", nil)
	rec := httptest.NewRecorder()

	h.Health(rec, req)

	if rec.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", rec.Code)
	}
}

/*
func TestHandler_AuthMiddleware(t *testing.T) {
	h := newTestHandler()

	// test the actual handler that middleware protects
	next := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})
	wrapped := h.AuthMiddleware("secret-token", next)

	// No token
	req1 := httptest.NewRequest("GET", "/", nil)
	rec1 := httptest.NewRecorder()
	wrapped.ServeHTTP(rec1, req1)
	if rec1.Code != http.StatusForbidden {
		t.Errorf("expected 403 without token, got %d", rec1.Code)
	}

	// Wrong token
	req2 := httptest.NewRequest("GET", "/", nil)
	req2.Header.Set("Authorization", "Bearer wrong")
	rec2 := httptest.NewRecorder()
	wrapped.ServeHTTP(rec2, req2)
	if rec2.Code != http.StatusForbidden {
		t.Errorf("expected 403 with wrong token, got %d", rec2.Code)
	}

	// Correct token
	req3 := httptest.NewRequest("GET", "/", nil)
	req3.Header.Set("Authorization", "Bearer secret-token")
	rec3 := httptest.NewRecorder()
	wrapped.ServeHTTP(rec3, req3)
	if rec3.Code != http.StatusOK {
		t.Errorf("expected 200 with correct token, got %d", rec3.Code)
	}
}
*/
