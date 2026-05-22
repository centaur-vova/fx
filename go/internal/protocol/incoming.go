package protocol

import "encoding/json"

// Interface for all incoming messages
type IncomingHandler interface {
	Handle(data json.RawMessage) ([]byte, error)
}

type PingMessage struct{}

func (p *PingMessage) Handle(data json.RawMessage) ([]byte, error) {
	var payload struct {
		TS float64 `json:"ts"`
	}
	json.Unmarshal(data, &payload)

	// Return pong
	return json.Marshal(map[string]any{
		"event": "pong",
		"data":  map[string]any{"ts": payload.TS},
	})
}
