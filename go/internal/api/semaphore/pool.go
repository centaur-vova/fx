package semaphore

import (
	"context"
	"fast-atomic-flow/go/internal/logger"
	"fmt"
	"time"
)

type Pool interface {
	Acquire(ctx context.Context, mc int, timeout, ttl time.Duration) (SlotUID, error)
	Release(sid SlotUID) error
}

// SlotUID is a compact string identifier for a distributed semaphore permit.
// Format: "mc:slotIdx" (e.g., "5:3" means mc=5, slot=3).
// It carries all information needed to release the slot from any API instance.
type SlotUID string

// NewSlotUID creates a SlotUID from mc and slot index.
func NewSlotUID(mc, slotIdx int) SlotUID {
	return SlotUID(fmt.Sprintf("%d:%d", mc, slotIdx))
}

// Parse extracts mc and slotIdx from the string.
func (s SlotUID) Parse() (mc, slotIdx int, err error) {
	n, err := fmt.Sscanf(string(s), "%d:%d", &mc, &slotIdx)
	if err != nil || n != 2 {
		logger.Error("Failed to parse SlotUID", "uid", string(s), "error", err)
		return 0, 0, fmt.Errorf("invalid SlotUID format: %s", s)
	}
	return mc, slotIdx, nil
}

func (s SlotUID) String() string {
	return string(s)
}
