package gateway

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/logger"
	"fast-atomic-flow/go/internal/protocol"
)

// MessageHandler is a function that processes a raw JSON message.
type MessageHandler func(json.RawMessage)

// MessageRouter routes incoming NATS messages to appropriate handlers
// based on their type field.
type MessageRouter struct {
	store    MetricsStore
	hub      Broadcaster
	handlers map[string]MessageHandler
}

type MetricsStore interface {
	IncTasksCreated(count int, maxConcurrent int, mode string)
	IncTasksCompleted(maxConcurrent int)
	IncTasksFailed(maxConcurrent int)
	IncTasksRetried(maxConcurrent int)
}

type Broadcaster interface {
	Broadcast(data any)
}

// NewMessageRouter creates a MessageRouter with all handlers registered.
func NewMessageRouter(store MetricsStore, hub Broadcaster) *MessageRouter {
	r := &MessageRouter{
		store: store,
		hub:   hub,
	}
	r.handlers = map[string]MessageHandler{
		"task.batch.created": r.handleBatchCreated,
		"task.status.update": r.handleTaskStatusUpdate,
	}
	return r
}

// Route dispatches an incoming message to the registered handler.
// If no handler is found, the message is silently ignored.
func (r *MessageRouter) Route(envType string, data json.RawMessage) {
	handler, ok := r.handlers[envType]
	if !ok {
		return
	}
	handler(data)
}

func (r *MessageRouter) handleBatchCreated(data json.RawMessage) {
	var msg protocol.TaskBatchCreated
	if err := json.Unmarshal(data, &msg); err != nil {
		logger.Error("🧩 Failed to unmarshal task.batch.created", "error", err)
		return
	}

	// Metrics
	r.store.IncTasksCreated(
		int(msg.Count),
		int(msg.MC),
		msg.Mode,
	)
}

func (r *MessageRouter) handleTaskStatusUpdate(data json.RawMessage) {
	var msg protocol.TaskStatusUpdate
	if err := json.Unmarshal(data, &msg); err != nil {
		logger.Error("🧩 Failed to unmarshal task.status.update", "error", err)
		return
	}

	switch msg.Status {
	case "completed":
		r.store.IncTasksCompleted(int(msg.MC))
	case "retries_failed":
		r.store.IncTasksFailed(int(msg.MC))
	case "retry":
		r.store.IncTasksRetried(int(msg.MC))
	}

	r.hub.Broadcast(&msg)
}
