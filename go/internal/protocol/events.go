package protocol

const MagicByte byte = 0x02

const (
	StatusQueued        byte = iota // 0
	StatusProcessing                // 1
	StatusCheckLock                 // 2
	StatusProgress                  // 3
	StatusCompleted                 // 4
	StatusLockAcquired              // 5
	StatusLockFailed                // 6
	StatusRetriesFailed             // 7
)

var StatusMap = map[string]byte{
	"queued":         StatusQueued,
	"processing":     StatusProcessing,
	"check_lock":     StatusCheckLock,
	"progress":       StatusProgress,
	"completed":      StatusCompleted,
	"lock_acquired":  StatusLockAcquired,
	"lock_failed":    StatusLockFailed,
	"retries_failed": StatusRetriesFailed,
}
