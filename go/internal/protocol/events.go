// Package protocol defines binary wire formats and constants for WebSocket communication.
package protocol

// MagicByte identifies binary frames from Fast AF protocol.
const MagicByte byte = 0x02

// Task status codes for binary packing (3 bits used).
const (
	StatusCheckLock     byte = iota // 0  \
	StatusProgress                  // 1   \
	StatusCompleted                 // 2    3 bits
	StatusLockAcquired              // 3   /
	StatusLockFailed                // 4  /
	StatusRetriesFailed             // 5 /
	StatusRetry                     // 6
)

// Semaphore driver codes for binary packing (1 bit used).
const (
	SemaphoreShared byte = iota // 0 \ 1 bit
	SemaphoreAPI                // 1 /
)

// Task mode codes for binary packing (1 bit used).
const (
	TaskModeObservation byte = iota // 0 \ 1 bit
	TaskModeStress                  // 1 /
)

// Message type constants for NATS envelopes.
const (
	MsgTypeBatchCreated = "task.batch.created"
	MsgTypeStatusUpdate = "task.status.update"
)

// StatusMap maps string status to binary codes.
var StatusMap = map[string]byte{
	"check_lock":     StatusCheckLock,
	"progress":       StatusProgress,
	"completed":      StatusCompleted,
	"lock_acquired":  StatusLockAcquired,
	"lock_failed":    StatusLockFailed,
	"retries_failed": StatusRetriesFailed,
	"retry":          StatusRetry,
}

// SemaphoreMap maps driver names to binary codes.
var SemaphoreMap = map[string]byte{
	"shared": SemaphoreShared,
	"api":    SemaphoreAPI,
}

// TaskModeMap maps mode strings to binary codes.
var TaskModeMap = map[string]byte{
	"observation": TaskModeObservation,
	"stress":      TaskModeStress,
}
