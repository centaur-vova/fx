<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Contract\Support\FromArray;
use App\Contract\Support\Identifiable;
use App\Contract\Task\SemaphoreDriver;

/**
 * Payload for Swoole Task Worker execution
 */
final readonly class TaskExecutionPayload implements Identifiable, FromArray
{
    /**
     * @see \App\Service\Provider\Task\SemaphoreProvider
     */
    public function __construct(
        public int $id,
        public int $mc,
        public string $mode,
        public SemaphoreDriver $sem,
        public int $attempt = 0,
    ) {
    }

    /**
     * Create a new task payload with a random 31-bit ID.
     *
     * The highest bit (bit 31) is reserved for the semaphore driver type
     * when packed into the binary WebSocket frame.
     *
     * @see go/internal/protocol/messages.go TaskStatusUpdate.Pack()
     * @see resources/js/modules/decoder.js taskId unpacking
     */
    public static function create(int $mc, string $mode, SemaphoreDriver $sem): self
    {
        // It's magic..
        $id = random_int(0, 0x7FFFFFFF);

        return new self(
            id: $id,
            mc: $mc,
            mode: $mode,
            sem: $sem,
        );
    }

    public function incrAttempt(): self
    {
        return new self(
            id: $this->id,
            mc: $this->mc,
            mode: $this->mode,
            sem: $this->sem,
            attempt: $this->attempt + 1,
        );
    }

    public function getId(): string
    {
        return (string) $this->id;
    }

    /**
     * @param array{id: int|string, mc: int|string, mode: string, sem: string, attempt?: int|string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            mc: (int) $data['mc'],
            mode: (string) $data['mode'],
            sem: SemaphoreDriver::from($data['sem']),
            attempt: (int) ($data['attempt'] ?? 0),
        );
    }
}
