package task

import (
	"encoding/json"
	"fast-atomic-flow/go/internal/protocol"
	"net/http"
)

type Publisher interface {
	Publish(subj string, data []byte) error
}

type Handler struct {
	publisher Publisher // nats conn for now
	subject   string    // nats subject for now
}

func NewHandler(publisher Publisher, subject string) *Handler {
	return &Handler{
		publisher: publisher,
		subject:   subject,
	}
}

// @Summary SendStatus receives task status update and publishes to NATS.
// @Accept  json
// @Param   status body protocol.TaskStatusUpdate true "Task status"
// @Success 202
// @Failure 400 {string} string "invalid JSON body"
// @Failure 500 {string} string "failed to publish to NATS"
// @Router  /task/status [post]
func (h *Handler) SendStatus(w http.ResponseWriter, r *http.Request) {
	var req protocol.TaskStatusUpdate

	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "invalid JSON body: "+err.Error(), http.StatusBadRequest)
		return
	}

	// Serialize back to JSON
	// Marshal just the status first
	statusBytes, err := json.Marshal(req)
	if err != nil {
		http.Error(w, "failed to marshal status: "+err.Error(), http.StatusInternalServerError)
		return
	}
	payload, err := json.Marshal(protocol.NatsEnvelope{
		Type: protocol.MsgTypeStatusUpdate,
		Data: statusBytes,
	})
	if err != nil {
		http.Error(w, "failed to marshal task status: "+err.Error(), http.StatusInternalServerError)
		return
	}

	// Publish status to NATS
	if err := h.publisher.Publish(h.subject, payload); err != nil {
		http.Error(w, "failed to publish to NATS: "+err.Error(), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusAccepted)
}
