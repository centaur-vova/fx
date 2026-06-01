package protocol

import (
	"encoding/binary"
	"encoding/json"
)

type BinaryPacker interface {
	Pack() []byte
}

type WsEvent struct {
	Event string `json:"event"`
	Data  any    `json:"data"`
}

func NewEvent(name string, data any) WsEvent {
	return WsEvent{
		Event: name,
		Data:  data,
	}
}

// In-place json marshalling
func (e WsEvent) Marshal() []byte {
	b, _ := json.Marshal(e)
	return b
}

// Wrapper for incoming serialized messages from NATS
type NatsEnvelope struct {
	Type string          `json:"_t"`
	Data json.RawMessage `json:"d"`
}

type NatsStats struct {
	Messages  uint64 `json:"messages"`
	Bytes     uint64 `json:"bytes"`
	Consumers int    `json:"consumers"`
}
type SystemMetrics struct {
	Connections int     `json:"connections"`
	MemoryMb    float64 `json:"memory_mb"`
	FreeMemMb   float64 `json:"free_mem"`
	CPUUsage    float64 `json:"cpu_usage"`
	NatsStats   `json:"nats_stats"`
}

type WelcomeData struct {
	WorkerNum       int    `json:"worker_num"`
	CPUCores        int    `json:"cpu_cores"`
	QueueCapacity   int    `json:"queue_capacity"`
	AppVersion      string `json:"app_version"`
	BuildDate       string `json:"build_date"`
	StreamCreatedAt string `json:"stream_created_at"`
	WorkerLabel     string `json:"worker_label"`
}

type TaskBatchCreated struct {
	Count uint16 `json:"count"`
	MC    uint8  `json:"mc"`
	Mode  string `json:"mode"`
}
type TaskStatusUpdate struct {
	ID       uint32 `json:"id"`
	Status   string `json:"status" enums:"check_lock,progress,completed,lock_acquired,lock_failed,retries_failed,retry"`
	MC       uint8  `json:"mc" minimum:"1" maximum:"255" default:"1"`
	Progress uint8  `json:"progress" default:"0"`
	Worker   uint8  `json:"worker" default:"0"`
	Sem      string `json:"sem" enums:"shared,api"`
	Mode     string `json:"mode" enums:"observation,stress"`
}

func (t *TaskStatusUpdate) Pack() []byte {
	buf := make([]byte, 9)
	buf[0] = MagicByte

	val, ok := StatusMap[t.Status]
	if !ok {
		val = 255
	}
	buf[1] = val

	// Pack task ID (31 bits) and semaphore type (1 bit) into a single uint32.
	// Upper bit = semaphore driver (0 for shared, 1 for API).
	// Lower 31 bits = task ID.
	semBit, ok := SemaphoreMap[t.Sem]
	if !ok {
		semBit = 0
	}
	packed32 := (t.ID & 0x7FFFFFFF) | (uint32(semBit) << 31)
	binary.BigEndian.PutUint32(buf[2:6], packed32)

	buf[6] = t.MC

	// Pack progress (7 bits) and task mode (1 bit) into a single byte.
	// Upper bit = task mode (0 for observation, 1 for stress).
	// Lower 7 bits = progress (0–100).
	modeBit, ok := TaskModeMap[t.Mode]
	if !ok {
		modeBit = 0
	}
	packed8 := (t.Progress & 0x7F) | (modeBit << 7)
	buf[7] = packed8

	buf[8] = t.Worker

	return buf
}
