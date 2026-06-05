// Package auth provides JWT token generation and authentication handlers.
package auth

import (
	"fast-atomic-flow/go/internal/api/response"
	"fast-atomic-flow/go/internal/clock"
	"net/http"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

// DefaultTokenTTL is the default lifetime of a JWT token (24 hours).
const DefaultTokenTTL = 24 * time.Hour

// Handler handles JWT token generation.
type Handler struct {
	jwtSecret string
	clock     clock.Clock
}

// TokenResponse contains the generated JWT token and its expiration time.
type TokenResponse struct {
	Token     string `json:"token"`
	ExpiresAt int64  `json:"expires_at"`
}

// NewHandler creates an auth handler with the given JWT secret and clock.
func NewHandler(jwtSecret string, cl clock.Clock) *Handler {
	return &Handler{
		jwtSecret: jwtSecret,
		clock:     cl,
	}
}

// GenerateToken handles POST /auth/token
// @Summary      Generate JWT token
// @Security     ApiKeyAuth
// @Description  Returns a signed JWT token valid for 24 hours
// @Tags         auth
// @Accept       json
// @Produce      json
// @Success      200 {object} TokenResponse
// @Failure      400 {string} string "invalid request body"
// @Failure      401 {string} string "Unauthorized"
// @Failure      500 {string} string "internal server error"
// @Router       /auth/token [post]
func (h *Handler) GenerateToken(w http.ResponseWriter, _ *http.Request) {
	now := h.clock.Now()
	expiresAt := now.Add(DefaultTokenTTL).Unix()

	claims := jwt.MapClaims{
		"sub": "api-client",
		"exp": expiresAt,
		"iat": now.Unix(),
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	tokenString, err := token.SignedString([]byte(h.jwtSecret))
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)

	resp := TokenResponse{
		Token:     tokenString,
		ExpiresAt: expiresAt,
	}
	response.WriteJSON(w, resp)
}
