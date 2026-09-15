// Package semaphore provides Redis-based distributed semaphore implementation.
package semaphore

import (
	"context"
	"errors"
	"fast-atomic-flow/go/internal/clock"
	"fast-atomic-flow/go/internal/embed"
	"fast-atomic-flow/go/internal/logger"
	"fmt"
	"time"

	"github.com/redis/go-redis/v9"
)

// RedisPool implements a distributed semaphore using Redis and Lua scripts.
type RedisPool struct {
	client        *redis.Client
	acquireScript *redis.Script
	releaseScript *redis.Script
	clock         clock.Clock
}

// NewRedisPool creates a RedisPool with the given Redis client and clock.
func NewRedisPool(client *redis.Client, cl clock.Clock) *RedisPool {
	return &RedisPool{
		client:        client,
		acquireScript: redis.NewScript(embed.LoadLua("acquire.lua")),
		releaseScript: redis.NewScript(embed.LoadLua("release.lua")),
		clock:         cl,
	}
}

// Acquire tries to get a slot in the distributed semaphore.
// mc — max concurrent slots.
// timeout — how long to wait for a free slot.
// ttl — how long the permit lives before auto-expiry.
func (p *RedisPool) Acquire(ctx context.Context, mc int, timeout, ttl time.Duration) (SlotUID, error) {
	// Build Redis keys for this semaphore
	activeKey := fmt.Sprintf("{semaphore:%d}:active", mc)
	channel := fmt.Sprintf("{semaphore:%d}:events", mc)

	// Create context with deadline ONCE
	ctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()

	var pubsub *redis.PubSub
	var ch <-chan *redis.Message

	for {
		result, err := p.acquireScript.Run(
			ctx,
			p.client,
			[]string{activeKey},
			mc,
			int(ttl.Seconds()),
		).Result()

		if err != nil && !errors.Is(err, redis.Nil) {
			return "", fmt.Errorf("acquire script error: %w", err)
		}

		// Success
		if slotIdx, ok := result.(int64); ok {
			return NewSlotUID(mc, int(slotIdx)), nil
		}

		// Lazy pubsub creation — only if first attempt failed
		if pubsub == nil {
			pubsub = p.client.Subscribe(ctx, channel)
			defer func() {
				if err := pubsub.Close(); err != nil {
					logger.Warn("Error closing pubsub", "pkg", "semaphore", "func", "Acquire", "error", err)
				}
			}()
			ch = pubsub.Channel(redis.WithChannelSize(1000))
		}

		// Wait for event or timeout
		select {
		case <-ch:
			// Slot freed, retry
			continue
		case <-ctx.Done():
			if errors.Is(ctx.Err(), context.DeadlineExceeded) {
				return "", fmt.Errorf("acquire timeout after %v", timeout)
			}
			return "", ctx.Err()
		}
	}
}

// Release releases a previously acquired semaphore slot by its UID.
func (p *RedisPool) Release(sid SlotUID) error {
	mc, slotIdx, err := sid.Parse()
	if err != nil {
		return err
	}

	activeKey := fmt.Sprintf("{semaphore:%d}:active", mc)
	channel := fmt.Sprintf("{semaphore:%d}:events", mc)

	_, err = p.releaseScript.Run(
		context.Background(),
		p.client,
		[]string{activeKey, channel},
		slotIdx,
	).Result()
	return err
}
