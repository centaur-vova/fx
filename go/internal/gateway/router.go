package gateway

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/protocol"
)

type Router struct {
	handlers map[string]protocol.IncomingHandler
}

func NewRouter() *Router {
	r := &Router{handlers: make(map[string]protocol.IncomingHandler)}

	// Register handlers
	r.handlers["ping"] = &protocol.PingMessage{}
	return r
}

func (r *Router) Route(raw []byte) ([]byte, error) {
	var msg struct {
		Event string          `json:"event"`
		Data  json.RawMessage `json:"data"`
	}

	if err := json.Unmarshal(raw, &msg); err != nil {
		return nil, err
	}

	if handler, ok := r.handlers[msg.Event]; ok {
		return handler.Handle(msg.Data)
	}

	return nil, nil // Unknown message type, discard
}
