package balancer

import (
	"bytes"
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
	u.ApiInstances[1].SetUnalive(false)

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

// ============ Unalive/Review ==========
func TestForceUnaliveHandler_Success(t *testing.T) {
	u := NewUpstream(Config{})
	// Register a test instance
	u.RegisterInstance("http://localhost:8081")

	// Find the instance hash
	var hash string
	u.mu.RLock()
	for _, inst := range u.ApiInstances {
		hash = inst.Hash
		break
	}
	u.mu.RUnlock()

	reqBody := bytes.NewBufferString(`{"hash":"` + hash + `"}`)
	req := httptest.NewRequest("POST", "/instance/unalive", reqBody)
	rec := httptest.NewRecorder()

	handler := ForceUnaliveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)

	var resp map[string]string
	err := json.Unmarshal(rec.Body.Bytes(), &resp)
	assert.NoError(t, err)
	assert.Equal(t, "ok", resp["status"])
	assert.Contains(t, resp["message"], "marked as dead")
}

func TestForceUnaliveHandler_InvalidJSON(t *testing.T) {
	u := NewUpstream(Config{})
	req := httptest.NewRequest("POST", "/instance/unalive", bytes.NewBufferString(`{invalid`))
	rec := httptest.NewRecorder()

	handler := ForceUnaliveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusBadRequest, rec.Code)
}

func TestForceUnaliveHandler_MissingHash(t *testing.T) {
	u := NewUpstream(Config{})
	reqBody := bytes.NewBufferString(`{}`)
	req := httptest.NewRequest("POST", "/instance/unalive", reqBody)
	rec := httptest.NewRecorder()

	handler := ForceUnaliveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusBadRequest, rec.Code)

	var resp map[string]string
	err := json.Unmarshal(rec.Body.Bytes(), &resp)
	assert.NoError(t, err)
	assert.Equal(t, "hash parameter required", resp["error"])
}

func TestForceUnaliveHandler_InstanceNotFound(t *testing.T) {
	u := NewUpstream(Config{})
	reqBody := bytes.NewBufferString(`{"hash":"nonexistent"}`)
	req := httptest.NewRequest("POST", "/instance/unalive", reqBody)
	rec := httptest.NewRecorder()

	handler := ForceUnaliveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusNotFound, rec.Code)

	var resp map[string]string
	err := json.Unmarshal(rec.Body.Bytes(), &resp)
	assert.NoError(t, err)
	assert.Equal(t, "instance not found", resp["error"])
}

func TestReviveHandler_Success(t *testing.T) {
	u := NewUpstream(Config{})
	u.RegisterInstance("http://localhost:8081")

	// Get hash
	var hash string
	u.mu.RLock()
	for _, inst := range u.ApiInstances {
		hash = inst.Hash
		break
	}
	u.mu.RUnlock()

	// First force unalive
	var target *ApiInstance
	u.mu.Lock()
	for _, inst := range u.ApiInstances {
		if inst.Hash == hash {
			target = inst
			break
		}
	}
	if target != nil {
		target.SetUnalive(true)
	}
	u.mu.Unlock()

	// Then revive
	reqBody := bytes.NewBufferString(`{"hash":"` + hash + `"}`)
	req := httptest.NewRequest("POST", "/instance/revive", reqBody)
	rec := httptest.NewRecorder()

	handler := ReviveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)

	var resp map[string]string
	err := json.Unmarshal(rec.Body.Bytes(), &resp)
	assert.NoError(t, err)
	assert.Equal(t, "ok", resp["status"])
	assert.Contains(t, resp["message"], "revived")
}

func TestReviveHandler_InvalidJSON(t *testing.T) {
	u := NewUpstream(Config{})
	req := httptest.NewRequest("POST", "/instance/revive", bytes.NewBufferString(`{invalid`))
	rec := httptest.NewRecorder()

	handler := ReviveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusBadRequest, rec.Code)
}

func TestReviveHandler_MissingHash(t *testing.T) {
	u := NewUpstream(Config{})
	reqBody := bytes.NewBufferString(`{}`)
	req := httptest.NewRequest("POST", "/instance/revive", reqBody)
	rec := httptest.NewRecorder()

	handler := ReviveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusBadRequest, rec.Code)
}

func TestReviveHandler_InstanceNotFound(t *testing.T) {
	u := NewUpstream(Config{})
	reqBody := bytes.NewBufferString(`{"hash":"nonexistent"}`)
	req := httptest.NewRequest("POST", "/instance/revive", reqBody)
	rec := httptest.NewRecorder()

	handler := ReviveHandler(u)
	handler.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusNotFound, rec.Code)
}
