// Package response provides helper functions for writing JSON HTTP responses.
package response

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/logger"
	"net/http"
)

// WriteJSON encodes v to JSON and writes it to the response.
// If encoding fails, it logs the error. Headers should already be set.
func WriteJSON(w http.ResponseWriter, v any) {
	if err := json.NewEncoder(w).Encode(v); err != nil {
		logger.Error("Failed to write JSON response (client likely disconnected)", "error", err)
	}
}
