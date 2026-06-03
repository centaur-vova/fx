package auth

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestGenerateToken_Success(t *testing.T) {
	handler := NewAuthHandler("test-jwt-secret")

	req := httptest.NewRequest(http.MethodPost, "/auth/token", nil)
	req.Header.Set("Content-Type", "application/json")
	rec := httptest.NewRecorder()

	handler.GenerateToken(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)

	var resp TokenResponse
	err := json.NewDecoder(rec.Body).Decode(&resp)
	assert.NoError(t, err)
	assert.NotEmpty(t, resp.Token)
}
