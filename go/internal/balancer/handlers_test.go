package balancer

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/stretchr/testify/assert"
)

// ========== RegisterHandler ==========

func TestRegisterHandler_Success(t *testing.T) {
	u := NewUpstream(Config{})
	handler := RegisterHandler(u)

	req := httptest.NewRequest("POST", "/register", strings.NewReader("http://a:8081"))
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)
	assert.Equal(t, 1, len(u.ApiInstances))
	assert.Equal(t, "http://a:8081", u.ApiInstances[0].URL.String())
}

func TestRegisterHandler_EmptyBody(t *testing.T) {
	u := NewUpstream(Config{})
	handler := RegisterHandler(u)

	req := httptest.NewRequest("POST", "/register", nil)
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)
	assert.Equal(t, 0, len(u.ApiInstances))
}

// ========== HealthHandler ==========

func TestHealthHandler(t *testing.T) {
	u := NewUpstream(Config{})
	u.RegisterInstance("http://a:8081")
	u.RegisterInstance("http://b:8082")
	u.ApiInstances[1].SetAlive(false)

	handler := HealthHandler(u)

	req := httptest.NewRequest("GET", "/health", nil)
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)
	assert.Equal(t, contentTypeJSON, rec.Header().Get("Content-Type"))

	var resp HealthResponse
	err := json.NewDecoder(rec.Body).Decode(&resp)
	assert.NoError(t, err)
	assert.Equal(t, uint64(1), resp.Up)
	assert.Equal(t, uint64(1), resp.Down)
}

// ========== ProxyHandler ==========

func TestProxyHandler_NoInstances(t *testing.T) {
	u := NewUpstream(Config{})
	handler := ProxyHandler(u)

	req := httptest.NewRequest("GET", "/anything", nil)
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusServiceUnavailable, rec.Code)
}
