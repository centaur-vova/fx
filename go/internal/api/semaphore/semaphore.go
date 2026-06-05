// Package semaphore provides local in-memory semaphore implementation.
package semaphore

import (
	"time"
)

// Permit represents an acquired semaphore permit.
type Permit struct {
	UID           uint64
	MaxConcurrent int
	ExpiresAt     time.Time
}

// Semaphore is a local in-memory semaphore using a buffered channel.
type Semaphore struct {
	slots chan struct{}
}

// NewSemaphore creates a local semaphore with the given capacity.
func NewSemaphore(capacity int) *Semaphore {
	return &Semaphore{
		slots: make(chan struct{}, capacity),
	}
}
