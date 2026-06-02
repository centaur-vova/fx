package middleware

import (
	"fast-atomic-flow/go/internal/logger"
	"net"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

type RLConfig struct {
	Requests  int
	WindowSec int
}

type RateLimiter struct {
	client *redis.Client
}

func NewRateLimiter(client *redis.Client) *RateLimiter {
	return &RateLimiter{
		client: client,
	}
}

func (rl *RateLimiter) Middleware(cfg RLConfig, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx := r.Context()

		key := "rate_limit:" + getClientIP(r)

		now := time.Now().UnixMicro()

		// Drop old items
		threshold := now - int64(cfg.WindowSec*1_000_000)
		_, err := rl.client.ZRemRangeByScore(ctx, key, "-inf", strconv.FormatInt(threshold, 10)).Result()
		if err != nil {
			logger.Debug("ZRemRangeByScore error",
				"key", key,
				"now", now,
			)
			http.Error(w, "Internal server error", http.StatusInternalServerError)
			return
		}

		count, err := rl.client.ZCard(ctx, key).Result()
		if err != nil || count >= int64(cfg.Requests) {
			http.Error(w, "Too many requests", http.StatusTooManyRequests)
			return
		}

		rl.client.ZAdd(ctx, key, redis.Z{
			Score:  float64(now),
			Member: now,
		})

		rl.client.Expire(ctx, key, time.Duration(cfg.WindowSec+60)*time.Second)

		next.ServeHTTP(w, r)
	}
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
