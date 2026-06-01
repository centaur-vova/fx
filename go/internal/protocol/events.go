package protocol

const MagicByte byte = 0x02

const (
	StatusCheckLock     byte = iota // 0  \
	StatusProgress                  // 1   \
	StatusCompleted                 // 2    3 bits
	StatusLockAcquired              // 3   /
	StatusLockFailed                // 4  /
	StatusRetriesFailed             // 5 /
	StatusRetry                     // 6
)

const (
	SemaphoreShared byte = iota // 0 \ 1 bit
	SemaphoreAPI                // 1 /
)

const (
	TaskModeObservation byte = iota // 0 \ 1 bit
	TaskModeStress                  // 1 /
)

const (
	MsgTypeBatchCreated = "task.batch.created"
	MsgTypeStatusUpdate = "task.status.update"
)

var StatusMap = map[string]byte{
	"check_lock":     StatusCheckLock,
	"progress":       StatusProgress,
	"completed":      StatusCompleted,
	"lock_acquired":  StatusLockAcquired,
	"lock_failed":    StatusLockFailed,
	"retries_failed": StatusRetriesFailed,
	"retry":          StatusRetry,
}

var SemaphoreMap = map[string]byte{
	"shared": SemaphoreShared,
	"api":    SemaphoreAPI,
}

var TaskModeMap = map[string]byte{
	"observation": TaskModeObservation,
	"stress":      TaskModeStress,
}
