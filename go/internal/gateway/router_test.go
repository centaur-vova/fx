package gateway

import (
	"encoding/json"
	"testing"

	"fast-atomic-flow/go/internal/protocol"

	"github.com/stretchr/testify/assert"
)

type mockMetricsStore struct {
	createdCount int
	createdMC    int
	createdMode  string
	completedMC  int
	failedMC     int
	retriedMC    int
}

func (m *mockMetricsStore) IncTasksCreated(count int, mc int, mode string) {
	m.createdCount = count
	m.createdMC = mc
	m.createdMode = mode
}
func (m *mockMetricsStore) IncTasksCompleted(mc int) { m.completedMC = mc }
func (m *mockMetricsStore) IncTasksFailed(mc int)    { m.failedMC = mc }
func (m *mockMetricsStore) IncTasksRetried(mc int)   { m.retriedMC = mc }

type mockBroadcaster struct {
	broadcasted any
}

func (m *mockBroadcaster) Broadcast(data any) {
	m.broadcasted = data
}

func TestRouter_Route_Ping(t *testing.T) {
	r := NewRouter()

	ping := map[string]any{"event": "ping", "data": map[string]any{"ts": 123}}
	raw, _ := json.Marshal(ping)

	result, err := r.Route(raw)

	assert.NoError(t, err)
	assert.NotNil(t, result)

	var resp struct {
		Event string `json:"event"`
		Data  struct {
			TS float64 `json:"ts"`
		} `json:"data"`
	}
	err = json.Unmarshal(result, &resp)
	assert.NoError(t, err)
	assert.Equal(t, "pong", resp.Event)
	assert.Equal(t, float64(123), resp.Data.TS)
}

func TestRouter_Route_UnknownEvent(t *testing.T) {
	r := NewRouter()

	msg := map[string]any{"event": "horse.gallop", "data": "fast"}
	raw, _ := json.Marshal(msg)

	result, err := r.Route(raw)

	assert.NoError(t, err)
	assert.Nil(t, result)
}

func TestRouter_Route_InvalidJSON(t *testing.T) {
	r := NewRouter()

	result, err := r.Route([]byte("not-json"))

	assert.Error(t, err)
	assert.Nil(t, result)
}

func TestRouter_Route_EmptyBody(t *testing.T) {
	r := NewRouter()

	result, err := r.Route([]byte{})

	assert.Error(t, err)
	assert.Nil(t, result)
}

func TestRouter_NewRouter_HasPingHandler(t *testing.T) {
	r := NewRouter()

	handler, ok := r.handlers["ping"]
	assert.True(t, ok)
	assert.NotNil(t, handler)
	assert.Implements(t, (*protocol.IncomingHandler)(nil), handler)
}

// ========== MessageRouter ==========

func TestMessageRouter_Route_BatchCreated(t *testing.T) {
	mockStore := &mockMetricsStore{}
	mockHub := &mockBroadcaster{}
	r := NewMessageRouter(mockStore, mockHub)

	payload := json.RawMessage(`{"count":100,"mc":5,"mode":"stress"}`)
	r.Route("task.batch.created", payload)

	assert.Equal(t, 100, mockStore.createdCount)
	assert.Equal(t, 5, mockStore.createdMC)
	assert.Equal(t, "stress", mockStore.createdMode)
}

func TestMessageRouter_Route_StatusUpdate_Completed(t *testing.T) {
	mockStore := &mockMetricsStore{}
	mockHub := &mockBroadcaster{}
	r := NewMessageRouter(mockStore, mockHub)

	payload := json.RawMessage(`{"id":42,"status":"completed","mc":10,"progress":100,"worker":3,"sem":"shared","mode":"observation"}`)
	r.Route(protocol.MsgTypeStatusUpdate, payload)

	assert.Equal(t, 10, mockStore.completedMC)
	assert.NotNil(t, mockHub.broadcasted)

	msg, ok := mockHub.broadcasted.(*protocol.TaskStatusUpdate)
	assert.True(t, ok)
	assert.Equal(t, uint32(42), msg.ID)
	assert.Equal(t, "completed", msg.Status)
}

func TestMessageRouter_Route_StatusUpdate_RetriesFailed(t *testing.T) {
	mockStore := &mockMetricsStore{}
	mockHub := &mockBroadcaster{}
	r := NewMessageRouter(mockStore, mockHub)

	payload := json.RawMessage(`{"id":1,"status":"retries_failed","mc":3}`)
	r.Route(protocol.MsgTypeStatusUpdate, payload)

	assert.Equal(t, 3, mockStore.failedMC)
}

func TestMessageRouter_Route_StatusUpdate_Retry(t *testing.T) {
	mockStore := &mockMetricsStore{}
	mockHub := &mockBroadcaster{}
	r := NewMessageRouter(mockStore, mockHub)

	payload := json.RawMessage(`{"id":2,"status":"retry","mc":7}`)
	r.Route(protocol.MsgTypeStatusUpdate, payload)

	assert.Equal(t, 7, mockStore.retriedMC)
}

func TestMessageRouter_Route_UnknownType(t *testing.T) {
	mockStore := &mockMetricsStore{}
	mockHub := &mockBroadcaster{}
	r := NewMessageRouter(mockStore, mockHub)

	// Should not panic
	r.Route("horse.gallop", json.RawMessage(`{"fast":true}`))

	assert.Equal(t, 0, mockStore.createdCount)
	assert.Nil(t, mockHub.broadcasted)
}

func TestMessageRouter_Route_InvalidJSON(t *testing.T) {
	mockStore := &mockMetricsStore{}
	mockHub := &mockBroadcaster{}
	r := NewMessageRouter(mockStore, mockHub)

	// Should not panic
	r.Route("task.batch.created", json.RawMessage(`not-json`))

	assert.Equal(t, 0, mockStore.createdCount)
}
