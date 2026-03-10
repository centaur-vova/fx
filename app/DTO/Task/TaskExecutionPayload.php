<?php

declare(strict_types=1);

namespace App\DTO\Task;

/**
 * Payload for Swoole Task Worker execution
 */
final readonly class TaskExecutionPayload
{
    public function __construct(
        public int $id,
        public int $mc,
        public string $mode,
        public int $attempt = 0,
    ) {
    }

    public function incrAttempt(): self
    {
        return new self(
            id: $this->id,
            mc: $this->mc,
            mode: $this->mode,
            attempt: $this->attempt + 1,
        );
    }
}
