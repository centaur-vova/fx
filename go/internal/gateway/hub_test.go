package gateway

import (
	"encoding/json"
	"sync"
	"testing"
	"time"

	"fast-atomic-flow/go/internal/protocol"

	"github.com/gorilla/websocket"
	"github.com/stretchr/testify/assert"
)

// ========== Helpers ==========

func newTestClient() *Client {
	return &Client{
		Send: make(chan ClientMessage, clientSendBufferSize),
	}
}

type testBinaryPacker struct {
	payload []byte
}

func (t testBinaryPacker) Pack() []byte {
	return t.payload
}

// ========== Count ==========

func TestHub_Count(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}

	assert.Equal(t, 0, h.Count())

	c1 := newTestClient()
	c2 := newTestClient()

	h.clientsMu.Lock()
	h.clients[c1] = true
	h.clients[c2] = true
	h.clientsMu.Unlock()

	assert.Equal(t, 2, h.Count())
	assert.Equal(t, 2, h.GetClientsCount())
}

// ========== Remove ==========

func TestHub_Remove(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := newTestClient()

	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	assert.Equal(t, 1, h.Count())

	h.Remove(c)

	assert.Equal(t, 0, h.Count())
	assert.True(t, c.closed.Load())

	// Channel should be closed
	_, ok := <-c.Send
	assert.False(t, ok)
}

func TestHub_Remove_Idempotent(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := newTestClient()

	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	h.Remove(c)
	h.Remove(c) // should not panic
	h.Remove(c)

	assert.Equal(t, 0, h.Count())
}

func TestHub_Remove_Concurrent(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := newTestClient()

	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	var wg sync.WaitGroup
	for i := 0; i < 10; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			h.Remove(c)
		}()
	}
	wg.Wait()

	assert.Equal(t, 0, h.Count())
}

// ========== Broadcast ==========

func TestHub_Broadcast_BinaryPacker(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := newTestClient()

	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	packer := testBinaryPacker{payload: []byte{0x01, 0x02, 0x03}}
	h.Broadcast(packer)

	select {
	case msg := <-c.Send:
		assert.Equal(t, websocket.BinaryMessage, msg.Kind)
		assert.Equal(t, []byte{0x01, 0x02, 0x03}, msg.Payload)
	case <-time.After(100 * time.Millisecond):
		t.Fatal("expected message on client.Send")
	}
}

func TestHub_Broadcast_JSON(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := newTestClient()

	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	data := map[string]string{"hello": "world"}
	h.Broadcast(data)

	select {
	case msg := <-c.Send:
		assert.Equal(t, websocket.TextMessage, msg.Kind)

		var decoded map[string]string
		err := json.Unmarshal(msg.Payload, &decoded)
		assert.NoError(t, err)
		assert.Equal(t, "world", decoded["hello"])
	case <-time.After(100 * time.Millisecond):
		t.Fatal("expected message on client.Send")
	}
}

func TestHub_Broadcast_BufferOverflow(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}

	// Client with tiny buffer to force overflow
	c := &Client{
		Send: make(chan ClientMessage, 1), // buffer size 1
	}
	// Fill the buffer
	c.Send <- ClientMessage{Kind: websocket.TextMessage, Payload: []byte("block")}

	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	assert.Equal(t, 1, h.Count())

	// This should overflow and remove the client
	h.Broadcast(map[string]string{"overflow": "true"})

	// Give goroutine time to remove
	time.Sleep(50 * time.Millisecond)

	assert.Equal(t, 0, h.Count())
	assert.True(t, c.closed.Load())
}

func TestHub_Broadcast_NoClients(_ *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}

	// Should not panic
	h.Broadcast(map[string]string{"no": "clients"})
}

func TestHub_SendToClient(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := newTestClient()

	packer := testBinaryPacker{payload: []byte{0xFF}}
	h.SendToClient(c, packer)

	select {
	case msg := <-c.Send:
		assert.Equal(t, websocket.BinaryMessage, msg.Kind)
		assert.Equal(t, []byte{0xFF}, msg.Payload)
	case <-time.After(100 * time.Millisecond):
		t.Fatal("expected message on client.Send")
	}
}

func TestHub_SendToClient_Overflow(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := &Client{
		Send: make(chan ClientMessage), // unbuffered, will always overflow
	}
	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	h.SendToClient(c, map[string]string{"drop": "me"})

	time.Sleep(50 * time.Millisecond)
	assert.True(t, c.closed.Load())
}

func TestHub_Broadcast_EventMessage(t *testing.T) {
	h := &Hub{clients: make(map[*Client]bool)}
	c := newTestClient()

	h.clientsMu.Lock()
	h.clients[c] = true
	h.clientsMu.Unlock()

	event := protocol.NewEvent("test.event", map[string]string{"key": "value"})
	h.Broadcast(event)

	select {
	case msg := <-c.Send:
		assert.Equal(t, websocket.TextMessage, msg.Kind)
		var decoded struct {
			Event string            `json:"event"`
			Data  map[string]string `json:"data"`
		}
		if err := json.Unmarshal(msg.Payload, &decoded); err != nil {
			t.Fatalf("failed to unmarshal message: %v", err)
		}
		assert.Equal(t, "test.event", decoded.Event)
		assert.Equal(t, "value", decoded.Data["key"])
	case <-time.After(100 * time.Millisecond):
		t.Fatal("expected message on client.Send")
	}
}
