package middleware

import (
	"fast-atomic-flow/go/internal/logger"
	"net/http"
)

// APIAuthMiddleware wraps a handler to check for a valid Bearer token
func APIAuthMiddleware(token string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer "+token {
			logger.Warn("⛔ Unauthorized access attempt",
				"remote_addr", r.RemoteAddr,
				"path", r.URL.Path,
			)
			http.Error(w, "Forbidden", http.StatusUnauthorized)
			return
		}
		next.ServeHTTP(w, r)
	}
}
