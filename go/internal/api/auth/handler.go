package auth

import (
	"encoding/json"
	"net/http"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

const DefaultTokenTTL = 24 * time.Hour

type AuthHandler struct {
	jwtSecret string
}

type TokenResponse struct {
	Token     string `json:"token"`
	ExpiresAt int64  `json:"expires_at"`
}

func NewAuthHandler(jwtSecret string) *AuthHandler {
	return &AuthHandler{
		jwtSecret: jwtSecret,
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
func (h *AuthHandler) GenerateToken(w http.ResponseWriter, r *http.Request) {
	expiresAt := time.Now().Add(DefaultTokenTTL).Unix()

	claims := jwt.MapClaims{
		"sub": "api-client",
		"exp": expiresAt,
		"iat": time.Now().Unix(),
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	tokenString, err := token.SignedString([]byte(h.jwtSecret))
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(TokenResponse{
		Token:     tokenString,
		ExpiresAt: expiresAt,
	})
}
