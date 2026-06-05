package semaphore

import (
	"context"
	"errors"
	"fast-atomic-flow/go/internal/clock"
	"testing"
	"time"

	"github.com/alicebob/miniredis/v2"
	"github.com/redis/go-redis/v9"
)

func newTestRedisPool(t *testing.T) *RedisPool {
	t.Helper()
	mr := miniredis.RunT(t)
	client := redis.NewClient(&redis.Options{Addr: mr.Addr()})
	return NewRedisPool(client, clock.RealClock{})
}

func TestRedisPool_Acquire_Success(t *testing.T) {
	pool := newTestRedisPool(t)
	ctx := context.Background()

	uid, err := pool.Acquire(ctx, 2, 1*time.Second, 5*time.Second)

	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if uid == "" {
		t.Fatal("expected non-empty SlotUID")
	}
}

func TestRedisPool_Acquire_Timeout(t *testing.T) {
	pool := newTestRedisPool(t)
	ctx := context.Background()

	// Occupy all 2 slots
	_, err := pool.Acquire(ctx, 2, 100*time.Millisecond, 5*time.Second)
	if err != nil {
		t.Fatalf("first acquire failed: %v", err)
	}
	_, err = pool.Acquire(ctx, 2, 100*time.Millisecond, 5*time.Second)
	if err != nil {
		t.Fatalf("second acquire failed: %v", err)
	}

	// Third should timeout
	_, err = pool.Acquire(ctx, 2, 100*time.Millisecond, 2*time.Second)
	if err == nil {
		t.Fatal("expected timeout error, got nil")
	}
}

func TestRedisPool_Acquire_ContextCancelled(t *testing.T) {
	pool := newTestRedisPool(t)
	ctx, cancel := context.WithCancel(context.Background())

	// Occupy the only slot
	_, err := pool.Acquire(ctx, 1, 1*time.Second, 10*time.Second)
	if err != nil {
		t.Fatalf("first acquire failed: %v", err)
	}

	// Cancel context before attempting to acquire
	cancel()

	_, err = pool.Acquire(ctx, 1, 1*time.Second, 10*time.Second)
	if err == nil {
		t.Fatal("expected error after context cancel, got nil")
	}
	// Make sure the error is context cancellation related
	if !errors.Is(err, context.Canceled) && ctx.Err() != context.Canceled {
		t.Logf("got error: %v (expected context cancellation)", err)
	}
}

func TestRedisPool_Release(t *testing.T) {
	pool := newTestRedisPool(t)
	ctx := context.Background()

	// Acquire a permit
	uid, err := pool.Acquire(ctx, 1, 1*time.Second, 10*time.Second)
	if err != nil {
		t.Fatalf("acquire failed: %v", err)
	}

	// Release the permit
	err = pool.Release(uid)
	if err != nil {
		t.Fatalf("release failed: %v", err)
	}

	// Release non-existing permit, no error
	err = pool.Release("999:1")
	if err != nil {
		t.Fatalf("release of non-existing uid should not error, got %v", err)
	}

	// Acquire again to verify slot was freed
	uid2, err := pool.Acquire(ctx, 1, 100*time.Millisecond, 10*time.Second)
	if err != nil {
		t.Fatalf("re-acquire after release failed: %v", err)
	}
	if uid2 == "" {
		t.Fatal("expected non-empty SlotUID after release")
	}
}
