package protocol

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/logger"
)

// Interface for all incoming messages
type IncomingHandler interface {
	Handle(data json.RawMessage) ([]byte, error)
}

type PingMessage struct{}

func (p *PingMessage) Handle(data json.RawMessage) ([]byte, error) {
	var payload struct {
		TS float64 `json:"ts"`
	}
	if err := json.Unmarshal(data, &payload); err != nil {
		logger.Error("🧩 Failed to unmarshal ping message", "error", err)
		return nil, err
	}

	// Return pong
	return json.Marshal(map[string]any{
		"event": "pong",
		"data":  map[string]any{"ts": payload.TS},
	})
}
