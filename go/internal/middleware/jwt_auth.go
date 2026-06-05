package middleware

import (
	"fast-atomic-flow/go/internal/clock"
	"fast-atomic-flow/go/internal/logger"
	"fmt"
	"net/http"
	"strings"

	"github.com/golang-jwt/jwt/v5"
)

// JWTAuthMiddleware validates JWT token from Authorization header
func JWTAuthMiddleware(secret string, clock clock.Clock, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		authHeader := r.Header.Get("Authorization")
		if authHeader == "" {
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}

		parts := strings.Split(authHeader, " ")
		if len(parts) != 2 || parts[0] != "Bearer" {
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}

		tokenString := parts[1]
		token, err := jwt.Parse(tokenString, func(token *jwt.Token) (any, error) {
			// Validate signing method
			if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
				return nil, fmt.Errorf("unexpected signing method: %v", token.Header["alg"])
			}

			return []byte(secret), nil
		})
		if err != nil {
			logger.Warn("Invalid JWT", "error", err)
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}

		// Check expiration
		if claims, ok := token.Claims.(jwt.MapClaims); ok {
			exp, err := claims.GetExpirationTime()
			if err != nil {
				http.Error(w, "Invalid token: missing or malformed expiration claim", http.StatusUnauthorized)
				return
			}

			if exp.Before(clock.Now()) {
				http.Error(w, "Token expired", http.StatusUnauthorized)
				return
			}
		}

		next.ServeHTTP(w, r)
	}
}
