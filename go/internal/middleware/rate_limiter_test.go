package middleware

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/alicebob/miniredis/v2"
	"github.com/redis/go-redis/v9"
	"github.com/stretchr/testify/assert"
)

func setupTestRedis(t *testing.T) (*redis.Client, func()) {
	// Start miniredis (in-memory Redis mock)
	mr, err := miniredis.Run()
	if err != nil {
		t.Fatalf("failed to start miniredis: %v", err)
	}

	// Create Redis client pointing to miniredis
	client := redis.NewClient(&redis.Options{
		Addr: mr.Addr(),
	})

	// Return cleanup function
	return client, func() {
		mr.Close()
	}
}

func TestRateLimiter_Middleware(t *testing.T) {
	redisClient, cleanup := setupTestRedis(t)
	defer cleanup()

	rl := NewRateLimiter(redisClient)
	cfg := RLConfig{Requests: 3, WindowSec: 1}

	var counter int
	handler := rl.Middleware(cfg, http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		counter++
		w.WriteHeader(http.StatusOK)
	}))

	req := httptest.NewRequest("GET", "/test", nil)
	req.RemoteAddr = "127.0.0.1:12345"

	// Should succeed: 3 requests within limit
	for i := 0; i < 3; i++ {
		w := httptest.NewRecorder()
		handler.ServeHTTP(w, req)
		assert.Equal(t, http.StatusOK, w.Code)
	}
	assert.Equal(t, 3, counter)

	// Should fail: 4th request exceeds limit
	w := httptest.NewRecorder()
	handler.ServeHTTP(w, req)
	assert.Equal(t, http.StatusTooManyRequests, w.Code)
	assert.Equal(t, 3, counter)

	// Wait for window to expire
	time.Sleep(1100 * time.Millisecond)

	// Should succeed again after window passes
	w = httptest.NewRecorder()
	handler.ServeHTTP(w, req)
	assert.Equal(t, http.StatusOK, w.Code)
	assert.Equal(t, 4, counter)
}

func TestRateLimiter_DifferentIPs(t *testing.T) {
	redisClient, cleanup := setupTestRedis(t)
	defer cleanup()

	rl := NewRateLimiter(redisClient)
	cfg := RLConfig{Requests: 2, WindowSec: 10}

	handler := rl.Middleware(cfg, http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))

	req1 := httptest.NewRequest("GET", "/test", nil)
	req1.RemoteAddr = "192.168.1.1:12345"

	req2 := httptest.NewRequest("GET", "/test", nil)
	req2.RemoteAddr = "192.168.1.2:12345"

	// First IP: 2 requests (hits limit)
	for i := 0; i < 2; i++ {
		w := httptest.NewRecorder()
		handler.ServeHTTP(w, req1)
		assert.Equal(t, http.StatusOK, w.Code)
	}

	// First IP: 3rd request should be rejected
	w := httptest.NewRecorder()
	handler.ServeHTTP(w, req1)
	assert.Equal(t, http.StatusTooManyRequests, w.Code)

	// Second IP: still can make 2 requests (separate counter)
	for i := 0; i < 2; i++ {
		w := httptest.NewRecorder()
		handler.ServeHTTP(w, req2)
		assert.Equal(t, http.StatusOK, w.Code)
	}
}
