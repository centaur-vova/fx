package semaphore

import (
	"context"
	"fmt"
	"log/slog"
	"sync"
	"time"
)

type Pool struct {
	mu         sync.RWMutex
	semaphores map[int]*Semaphore
	permits    map[uint64]*Permit
}

func NewPool() *Pool {
	return &Pool{
		semaphores: make(map[int]*Semaphore),
		permits:    make(map[uint64]*Permit),
	}
}

// Acquire attempts to get a slot in a semaphore with given max concurrency
func (p *Pool) Acquire(ctx context.Context, mc int, timeout, ttl time.Duration) (uint64, error) {
	p.mu.Lock()
	sem, ok := p.semaphores[mc]
	if !ok {
		sem = NewSemaphore(mc)
		p.semaphores[mc] = sem
	}
	p.mu.Unlock()

	// Use timer with defer Stop to prevent resource leaks
	t := time.NewTimer(timeout)
	defer t.Stop()

	select {
	case sem.slots <- struct{}{}:
		// Successfully acquired slot
	case <-t.C:
		return 0, fmt.Errorf("acquire timeout after %v", timeout)
	case <-ctx.Done():
		return 0, ctx.Err()
	}

	uid := sem.nextUID.Add(1)
	permit := &Permit{
		UID:           uid,
		MaxConcurrent: mc,
	}

	// Lock first
	p.mu.Lock()
	p.permits[uid] = permit
	// Set up auto-release timer (TTL)
	permit.timer = time.AfterFunc(ttl, func() {
		slog.Debug("TTL expired, auto-releasing permit", "uid", uid)
		p.Release(uid)
	})
	p.mu.Unlock()

	return uid, nil
}

// Release frees the slot and removes permit from the pool
func (p *Pool) Release(uid uint64) {
	p.mu.Lock()
	permit, ok := p.permits[uid]
	if !ok {
		p.mu.Unlock()
		return
	}

	// Make a copy of object data before deleting
	mc := permit.MaxConcurrent
	timer := permit.timer
	delete(p.permits, uid)
	p.mu.Unlock()

	// Stop TTL timer to prevent double release
	if timer != nil {
		timer.Stop()
	}

	p.mu.RLock()
	sem, ok := p.semaphores[mc]
	p.mu.RUnlock()

	if ok {
		select {
		case <-sem.slots:
			slog.Debug("slot released", "uid", uid)
		default:
			// This prevents blocking if the slot was somehow already cleared
			slog.Warn("attempted to release an empty slot", "uid", uid)
		}
	}
}

func (p *Pool) PermitCount() int {
	p.mu.RLock()
	defer p.mu.RUnlock()

	return len(p.permits)
}
