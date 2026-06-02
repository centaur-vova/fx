package middleware

import (
	"context"
	"fast-atomic-flow/go/internal/embed"
	"net"
	"net/http"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

const ttlBufferSec = 60

type RLConfig struct {
	Limit     int
	WindowSec int
}

type RateLimiter struct {
	client *redis.Client
	script *redis.Script
}

func NewRateLimiter(client *redis.Client) *RateLimiter {
	return &RateLimiter{
		client: client,
		script: redis.NewScript(embed.LoadLua("rate_limiter.lua")),
	}
}

func (rl *RateLimiter) Middleware(cfg RLConfig, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx := r.Context()

		key := "rate_limit:" + getClientIP(r)

		ok, err := rl.allow(ctx, cfg, key)
		if err != nil {
			http.Error(w, "Internal server error", http.StatusInternalServerError)
			return
		}
		if !ok {
			http.Error(w, "Too many requests", http.StatusTooManyRequests)
			return
		}

		next.ServeHTTP(w, r)
	}
}

// allow checks if request is within rate limit using atomic Lua script.
// Returns true if allowed, false if rate limit exceeded.
func (rl *RateLimiter) allow(ctx context.Context, cfg RLConfig, key string) (bool, error) {
	now := time.Now().UnixMicro()
	threshold := now - int64(cfg.WindowSec*1_000_000)
	ttl := cfg.WindowSec + ttlBufferSec

	result, err := rl.script.Run(ctx, rl.client, []string{key}, now, threshold, cfg.Limit, ttl).Int()
	if err != nil {
		return false, err
	}
	return result == 1, nil
}

func getClientIP(r *http.Request) string {
	// X-Forwarded-For
	forwarded := r.Header.Get("X-Forwarded-For")
	if forwarded != "" {
		ips := strings.Split(forwarded, ",")
		// Take the first one
		return strings.TrimSpace(ips[0])
	}

	// Check X-Real-IP (if configured)
	realIP := r.Header.Get("X-Real-IP")
	if realIP != "" {
		return realIP
	}

	ip, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return ip
}
