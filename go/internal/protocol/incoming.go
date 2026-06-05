// Package protocol defines message structures and handlers for WebSocket communication.
package protocol

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/logger"
)

// IncomingHandler defines the interface for processing incoming WebSocket messages.
type IncomingHandler interface {
	Handle(data json.RawMessage) ([]byte, error)
}

// PingMessage handles ping events and returns pong responses.
type PingMessage struct{}

// Handle processes a ping message and returns a pong with the original timestamp.
func (p *PingMessage) Handle(data json.RawMessage) ([]byte, error) {
	var payload struct {
		TS float64 `json:"ts"`
	}
	if err := json.Unmarshal(data, &payload); err != nil {
		logger.Error("Failed to unmarshal ping message", "error", err)
		return nil, err
	}

	// Return pong
	return json.Marshal(map[string]any{
		"event": "pong",
		"data":  map[string]any{"ts": payload.TS},
	})
}
