package protocol

import (
	"encoding/binary"
	"testing"

	"github.com/stretchr/testify/assert"
)

// ========== TaskStatusUpdate.Pack ==========

func TestTaskStatusUpdate_Pack_Basic(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:       42,
		Status:   "completed",
		MC:       5,
		Progress: 75,
		Worker:   3,
		Sem:      "shared",
		Mode:     "observation",
	}

	packed := msg.Pack()

	// 9 bytes total
	assert.Len(t, packed, 9)

	// Magic byte
	assert.Equal(t, MagicByte, packed[0])

	// Status
	assert.Equal(t, StatusMap["completed"], packed[1])

	// Packed ID + Sem
	packed32 := binary.BigEndian.Uint32(packed[2:6])
	id := packed32 & 0x7FFFFFFF
	semBit := (packed32 >> 31) & 1
	assert.Equal(t, uint32(42), id)
	assert.Equal(t, uint32(SemaphoreMap["shared"]), semBit)

	// MC
	assert.Equal(t, uint8(5), packed[6])

	// Packed progress + mode
	packed8 := packed[7]
	progress := packed8 & 0x7F
	modeBit := (packed8 >> 7) & 1
	assert.Equal(t, uint8(75), progress)
	assert.Equal(t, uint8(TaskModeMap["observation"]), modeBit)

	// Worker
	assert.Equal(t, uint8(3), packed[8])
}

func TestTaskStatusUpdate_Pack_API_Semaphore(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:     100,
		Status: "progress",
		Sem:    "api",
		Mode:   "stress",
	}

	packed := msg.Pack()

	packed32 := binary.BigEndian.Uint32(packed[2:6])
	semBit := (packed32 >> 31) & 1
	assert.Equal(t, uint32(1), semBit)

	packed8 := packed[7]
	modeBit := (packed8 >> 7) & 1
	assert.Equal(t, uint8(1), modeBit)
}

func TestTaskStatusUpdate_Pack_UnknownStatus(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:     1,
		Status: "galloping_pony", // unknown
	}

	packed := msg.Pack()
	assert.Equal(t, uint8(255), packed[1])
}

func TestTaskStatusUpdate_Pack_UnknownSemaphore(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:  1,
		Sem: "tarantool", // unknown
	}

	packed := msg.Pack()
	packed32 := binary.BigEndian.Uint32(packed[2:6])
	semBit := (packed32 >> 31) & 1
	assert.Equal(t, uint32(0), semBit) // falls back to 0
}

func TestTaskStatusUpdate_Pack_UnknownMode(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:   1,
		Mode: "gallop", // unknown
	}

	packed := msg.Pack()
	packed8 := packed[7]
	modeBit := (packed8 >> 7) & 1
	assert.Equal(t, uint8(0), modeBit) // falls back to 0
}

func TestTaskStatusUpdate_Pack_MaxID(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:     0x7FFFFFFF, // max 31-bit
		Status: "queued",
		Sem:    "api",
		Mode:   "stress",
	}

	packed := msg.Pack()
	packed32 := binary.BigEndian.Uint32(packed[2:6])
	id := packed32 & 0x7FFFFFFF
	assert.Equal(t, uint32(0x7FFFFFFF), id)

	// Sem bit should NOT corrupt the ID
	semBit := (packed32 >> 31) & 1
	assert.Equal(t, uint32(1), semBit)
}

func TestTaskStatusUpdate_Pack_ProgressZero(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:       1,
		Status:   "completed",
		Progress: 0,
	}

	packed := msg.Pack()
	packed8 := packed[7]
	progress := packed8 & 0x7F
	assert.Equal(t, uint8(0), progress)
}

func TestTaskStatusUpdate_Pack_ProgressMax(t *testing.T) {
	msg := &TaskStatusUpdate{
		ID:       1,
		Status:   "completed",
		Progress: 100,
	}

	packed := msg.Pack()
	packed8 := packed[7]
	progress := packed8 & 0x7F
	assert.Equal(t, uint8(100), progress)
}

// ========== WsEvent ==========

func TestNewEvent(t *testing.T) {
	event := NewEvent("welcome", WelcomeData{
		WorkerNum: 6,
		CPUCores:  4,
	})

	assert.Equal(t, "welcome", event.Event)

	data, ok := event.Data.(WelcomeData)
	assert.True(t, ok)
	assert.Equal(t, 6, data.WorkerNum)
	assert.Equal(t, 4, data.CPUCores)
}

func TestWsEvent_Marshal(t *testing.T) {
	event := NewEvent("pong", map[string]int{"ts": 123})
	marshaled := event.Marshal()

	assert.Contains(t, string(marshaled), `"event":"pong"`)
	assert.Contains(t, string(marshaled), `"ts":123`)
}

// ========== BinaryPacker interface ==========

func TestTaskStatusUpdate_Implements_BinaryPacker(t *testing.T) {
	var packer BinaryPacker = &TaskStatusUpdate{}
	assert.NotNil(t, packer)
}
