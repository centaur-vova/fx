package task

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"

	"fast-atomic-flow/go/internal/protocol"
)

// mockPublisher implements Publisher and captures published messages.
type mockPublisher struct {
	mu       sync.Mutex
	messages []publishedMsg
}

type publishedMsg struct {
	subject string
	data    []byte
}

func (m *mockPublisher) Publish(subject string, data []byte) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	// Copy data to avoid caller mutating it later
	cp := make([]byte, len(data))
	copy(cp, data)
	m.messages = append(m.messages, publishedMsg{subject: subject, data: cp})
	return nil
}

func (m *mockPublisher) lastMessage() *publishedMsg {
	m.mu.Lock()
	defer m.mu.Unlock()
	if len(m.messages) == 0 {
		return nil
	}
	return &m.messages[len(m.messages)-1]
}

func TestSendStatus_Success(t *testing.T) {
	pub := &mockPublisher{}
	subject := "task.status.update"
	handler := NewHandler(pub, subject)

	reqBody := protocol.TaskStatusUpdate{
		ID:       42,
		Status:   "check_lock",
		MC:       3,
		Progress: 50,
		Worker:   7,
		Sem:      "api",
		Mode:     "observation",
	}

	body, _ := json.Marshal(reqBody)
	req := httptest.NewRequest(http.MethodPost, "/task/status", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	rec := httptest.NewRecorder()

	handler.SendStatus(rec, req)

	// HTTP status
	if rec.Code != http.StatusAccepted {
		t.Errorf("expected 202, got %d", rec.Code)
	}

	// Message was published
	msg := pub.lastMessage()
	if msg == nil {
		t.Fatal("expected message to be published, got nil")
	}

	if msg.subject != subject {
		t.Errorf("expected subject %q, got %q", subject, msg.subject)
	}

	// Verify envelope structure
	var env protocol.NatsEnvelope
	if err := json.Unmarshal(msg.data, &env); err != nil {
		t.Fatalf("failed to unmarshal envelope: %v", err)
	}

	if env.Type != protocol.MsgTypeStatusUpdate {
		t.Errorf("expected type %q, got %q", protocol.MsgTypeStatusUpdate, env.Type)
	}

	// Verify inner data
	var status protocol.TaskStatusUpdate
	if err := json.Unmarshal(env.Data, &status); err != nil {
		t.Fatalf("failed to unmarshal status from envelope: %v", err)
	}

	if status.ID != reqBody.ID {
		t.Errorf("expected ID %d, got %d", reqBody.ID, status.ID)
	}
	if status.Status != reqBody.Status {
		t.Errorf("expected status %q, got %q", reqBody.Status, status.Status)
	}
	if status.MC != reqBody.MC {
		t.Errorf("expected mc %d, got %d", reqBody.MC, status.MC)
	}
	if status.Sem != reqBody.Sem {
		t.Errorf("expected sem %q, got %q", reqBody.Sem, status.Sem)
	}
	if status.Mode != reqBody.Mode {
		t.Errorf("expected mode %q, got %q", reqBody.Mode, status.Mode)
	}
}

func TestSendStatus_InvalidJSON(t *testing.T) {
	pub := &mockPublisher{}
	handler := NewHandler(pub, "task.status.update")

	body := bytes.NewReader([]byte(`{not valid json`))
	req := httptest.NewRequest(http.MethodPost, "/task/status", body)
	req.Header.Set("Content-Type", "application/json")
	rec := httptest.NewRecorder()

	handler.SendStatus(rec, req)

	if rec.Code != http.StatusBadRequest {
		t.Errorf("expected 400, got %d", rec.Code)
	}

	if pub.lastMessage() != nil {
		t.Error("expected no message on invalid input")
	}
}
