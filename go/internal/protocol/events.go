package protocol

const MagicByte byte = 0x02

const (
	StatusProcessing    byte = iota // 0 \
	StatusCheckLock                 // 1  \
	StatusProgress                  // 2   \
	StatusCompleted                 // 3    3 bits
	StatusLockAcquired              // 4   /
	StatusLockFailed                // 5  /
	StatusRetriesFailed             // 6 /
	StatusRetry                     // 7
)

const (
	SemaphoreShared byte = iota // 0 \ 1 bit
	SemaphoreAPI                // 1 /
)

var StatusMap = map[string]byte{
	"processing":     StatusProcessing,
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
