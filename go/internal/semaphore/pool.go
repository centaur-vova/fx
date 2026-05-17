package semaphore

import (
	"context"
	"fast-atomic-flow/go/internal/logger"
	"fmt"
	"sync"
	"sync/atomic"
	"time"
)

type Pool struct {
	mu         sync.RWMutex
	semaphores map[int]*Semaphore
	permits    map[uint64]*Permit
	nextUID    atomic.Uint64
}

func NewPool() *Pool {
	p := &Pool{
		semaphores: make(map[int]*Semaphore),
		permits:    make(map[uint64]*Permit),
	}

	p.startCleaner()

	return p
}

func (p *Pool) startCleaner() {
	ticker := time.NewTicker(1 * time.Second)
	go func() {
		for range ticker.C {
			p.cleanupExpired()
		}
	}()
}

func (p *Pool) cleanupExpired() {
	p.mu.Lock()
	defer p.mu.Unlock()

	now := time.Now()
	for uid, permit := range p.permits {
		if now.After(permit.ExpiresAt) {
			logger.Debug("TTL expired (cleaner)", "uid", uid)
			p.internalRelease(uid)
		}
	}
}

// internalRelease releases a semaphore slot without locking.
// Must be called while p.mu is already held.
func (p *Pool) internalRelease(uid uint64) {
	permit, ok := p.permits[uid]
	if !ok {
		return
	}

	mc := permit.MaxConcurrent
	delete(p.permits, uid)

	sem, ok := p.semaphores[mc]
	if ok {
		select {
		case <-sem.slots:
			logger.Debug("Slot released (cleaner)", "uid", uid)
		default:
			logger.Warn("⚠️ Attempted to release an empty slot (cleaner)", "uid", uid)
		}
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

	uid := p.nextUID.Add(1)
	permit := &Permit{
		UID:           uid,
		MaxConcurrent: mc,
		ExpiresAt:     time.Now().Add(ttl),
	}

	// Lock first
	p.mu.Lock()
	p.permits[uid] = permit
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
	delete(p.permits, uid)
	p.mu.Unlock()

	p.mu.RLock()
	sem, ok := p.semaphores[mc]
	p.mu.RUnlock()

	if ok {
		select {
		case <-sem.slots:
			logger.Debug("Slot released", "uid", uid)
		default:
			// This prevents blocking if the slot was somehow already cleared
			logger.Warn("⚠️ Attempted to release an empty slot", "uid", uid)
		}
	}
}

func (p *Pool) PermitCount() int {
	p.mu.RLock()
	defer p.mu.RUnlock()

	return len(p.permits)
}
