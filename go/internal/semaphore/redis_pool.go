package semaphore

import (
	"context"
	"errors"
	"fmt"
	"time"

	"github.com/redis/go-redis/v9"
)

type RedisPool struct {
	client        *redis.Client
	acquireScript *redis.Script
	releaseScript *redis.Script
}

func NewRedisPool(client *redis.Client) *RedisPool {
	return &RedisPool{
		client:        client,
		acquireScript: redis.NewScript(loadLua("acquire.lua")),
		releaseScript: redis.NewScript(loadLua("release.lua")),
	}
}

// Acquire tries to get a slot in the distributed semaphore.
// mc — max concurrent slots.
// timeout — how long to wait for a free slot.
// ttl — how long the permit lives before auto-expiry.
func (p *RedisPool) Acquire(ctx context.Context, mc int, timeout, ttl time.Duration) (SlotUID, error) {
	// Build Redis keys for this semaphore
	activeKey := fmt.Sprintf("semaphore:%d:active", mc)

	deadline := time.Now().Add(timeout)

	for {
		// Check timeout
		if time.Now().After(deadline) {
			return "", fmt.Errorf("acquire timeout after %v", timeout)
		}

		result, err := p.acquireScript.Run(
			ctx,
			p.client,
			// KEYS
			[]string{activeKey},
			// ARGV
			mc,                 // ARGV[1] — max concurrent slots
			int(ttl.Seconds()), // ARGV[2] — TTL in seconds
		).Result()

		// Handle Redis specific errors safely
		if err != nil {
			if errors.Is(err, redis.Nil) {
				// No free slots, wait and retry
				select {
				case <-time.After(100 * time.Millisecond):
					continue
				case <-ctx.Done():
					return "", ctx.Err()
				}
			}
			return "", fmt.Errorf("acquire script error: %w", err)
		}

		if slotIdx, ok := result.(int64); ok {
			return NewSlotUID(mc, int(slotIdx)), nil
		}

		// Something's weirdo. Invalid slotIdx and no error? Shouldn't happen, but retry
	}
}

func (p *RedisPool) Release(sid SlotUID) error {
	mc, slotIdx := sid.Parse()
	activeKey := fmt.Sprintf("semaphore:%d:active", mc)

	_, err := p.releaseScript.Run(
		context.Background(),
		p.client,
		[]string{activeKey},
		slotIdx,
	).Result()
	return err
}
