package semaphore

import (
	"context"
	"testing"
	"time"
)

func TestPool_Acquire_Success(t *testing.T) {
	pool := NewPool()
	ctx := context.Background()

	uid, err := pool.Acquire(ctx, 2, 1*time.Second, 5*time.Second)

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if uid == 0 {
		t.Fatalf("expected non-zero uid")
	}
	if pool.PermitCount() != 1 {
		t.Errorf("permit count = %d, want 1", pool.PermitCount())
	}
}

func TestPool_Acquire_Timeout(t *testing.T) {
	pool := NewPool()
	ctx := context.Background()

	_, err := pool.Acquire(ctx, 2, 10*time.Millisecond, 2*time.Second)
	if err != nil {
		t.Fatalf("first acquire failed: %v", err)
	}

	_, err = pool.Acquire(ctx, 2, 10*time.Millisecond, 2*time.Second)
	if err != nil {
		t.Fatalf("second acquire failed: %v", err)
	}

	_, err = pool.Acquire(ctx, 2, 10*time.Millisecond, 2*time.Second)
	if err == nil {
		t.Fatal("expected timeout error, got nil")
	}
}

func TestPool_Acquire_ContextCancelled(t *testing.T) {
	pool := NewPool()
	ctx, cancel := context.WithCancel(context.Background())

	// Occupy the only slot so Acquire blocks
	_, err := pool.Acquire(ctx, 1, 1*time.Second, 10*time.Second)
	if err != nil {
		t.Fatalf("first acquire failed: %v", err)
	}

	// Cancel context before attempting to acquire
	cancel()

	_, err = pool.Acquire(ctx, 1, 1*time.Second, 10*time.Second)
	if err != context.Canceled {
		t.Fatalf("expected context.Canceled, got %v", err)
	}
}

func TestPool_Release(t *testing.T) {
	pool := NewPool()
	ctx := context.Background()

	// Acquire a permit
	uid, err := pool.Acquire(ctx, 1, 1*time.Second, 10*time.Second)
	if err != nil {
		t.Fatalf("acquire failed: %v", err)
	}
	if pool.PermitCount() != 1 {
		t.Fatalf("expected 1 permit, got %d", pool.PermitCount())
	}

	// Release the permit
	pool.Release(uid)
	if pool.PermitCount() != 0 {
		t.Fatalf("expected 0 permits after release, got %d", pool.PermitCount())
	}

	// Release non-existing permit, no error
	pool.Release(999)

	// No panic here, oll korrect
}
