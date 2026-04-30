package semaphore

import (
	"sync/atomic"
	"time"
)

type Permit struct {
	UID           uint64
	MaxConcurrent int
	timer         *time.Timer
}

type Semaphore struct {
	slots   chan struct{}
	nextUID atomic.Uint64
}

func NewSemaphore(capacity int) *Semaphore {
	return &Semaphore{
		slots: make(chan struct{}, capacity),
	}
}
