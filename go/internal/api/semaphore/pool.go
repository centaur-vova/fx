package semaphore

import (
	"context"
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
func (s SlotUID) Parse() (mc, slotIdx int) {
	fmt.Sscanf(string(s), "%d:%d", &mc, &slotIdx)
	return
}

func (s SlotUID) String() string {
	return string(s)
}
