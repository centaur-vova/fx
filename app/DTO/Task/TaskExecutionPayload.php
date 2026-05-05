<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Contract\Support\Identifiable;

/**
 * Payload for Swoole Task Worker execution
 */
final readonly class TaskExecutionPayload implements Identifiable
{
    /**
     * @param string $sem Semaphore driver
     * @see \App\Service\Provider\Task\SemaphoreProvider
     */
    public function __construct(
        public int $id,
        public int $mc,
        public string $mode,
        public string $sem,
        public int $attempt = 0,
    ) {
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
}
