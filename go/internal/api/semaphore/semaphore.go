package semaphore

import (
	"time"
)

type Permit struct {
	UID           uint64
	MaxConcurrent int
	ExpiresAt     time.Time
}

type Semaphore struct {
	slots chan struct{}
}

func NewSemaphore(capacity int) *Semaphore {
	return &Semaphore{
		slots: make(chan struct{}, capacity),
	}
}
