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

type NatsStats struct {
	Messages  uint64 `json:"messages"`
	Bytes     uint64 `json:"bytes"`
	Consumers int    `json:"consumers"`
}
type SystemMetrics struct {
	Connections int     `json:"connections"`
	MemoryMb    float64 `json:"memory_mb"`
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
}

type TaskStatusUpdate struct {
	ID       uint64 `json:"id"`
	Status   string `json:"status"`
	MC       uint8  `json:"mc"`
	Progress uint8  `json:"progress"`
	Worker   uint8  `json:"worker"`
}

func (t *TaskStatusUpdate) Pack() []byte {
	buf := make([]byte, 13)
	buf[0] = MagicByte

	sByte, ok := StatusMap[t.Status]
	if !ok {
		sByte = 255
	}
	buf[1] = sByte

	binary.BigEndian.PutUint64(buf[2:10], t.ID)
	buf[10] = t.MC
	buf[11] = t.Progress
	buf[12] = t.Worker

	return buf
}
