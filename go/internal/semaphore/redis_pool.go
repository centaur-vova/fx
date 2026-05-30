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
	channel := fmt.Sprintf("semaphore:%d:events", mc)

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
		if err != nil && !errors.Is(err, redis.Nil) {
			return "", fmt.Errorf("acquire script error: %w", err)
		}

		// Success
		if slotIdx, ok := result.(int64); ok {
			return NewSlotUID(mc, int(slotIdx)), nil
		}

		// Subscribe for the events channel & wait until a slot is freed
		pubsub := p.client.Subscribe(ctx, channel)
		defer pubsub.Close()

		// No free slots, wait and retry
		select {
		case <-pubsub.Channel():
			// There's a free slot, try again
			continue
		case <-time.After(time.Until(deadline)):
			return "", fmt.Errorf("acquire timeout after %v", timeout)
		case <-ctx.Done():
			return "", ctx.Err()
		}
	}
}

func (p *RedisPool) Release(sid SlotUID) error {
	mc, slotIdx := sid.Parse()
	activeKey := fmt.Sprintf("semaphore:%d:active", mc)
	channelKey := fmt.Sprintf("semaphore:%d:events", mc)

	_, err := p.releaseScript.Run(
		context.Background(),
		p.client,
		[]string{activeKey, channelKey},
		slotIdx,
	).Result()
	return err
}
