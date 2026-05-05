<?php

declare(strict_types=1);

namespace App\DTO\Http\Request;

use App\Contract\Task\SemaphoreDriver;
use App\Exception\Http\InvalidTaskBatchException;

final readonly class CreateTasks
{
    public function __construct(
        public int $count,
        public int $maxConcurrent,
        public string $semaphoreDriver,
        public bool $stressMode,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var array{
         *      count?: int|string,
         *      max_concurrent?: int|string,
         *      semaphore_driver?: string,
         *      stress_mode?: bool|string
         *  } $payload
         */
        return new self(
            count: (int) ($payload['count'] ?? 1),
            maxConcurrent: (int) ($payload['max_concurrent'] ?? 2),
            semaphoreDriver: (string) ($payload['semaphore_driver'] ?? SemaphoreDriver::SHARED->value), // fallback to shared driver
            stressMode: (bool) ($payload['stress_mode'] ?? false),
        );
    }

    public function validate(int $maxBatchSize, int $semaphoreLimit): void
    {
        if ($this->count < 1 || $this->count > $maxBatchSize) {
            throw new InvalidTaskBatchException("Count must be between 1 and $maxBatchSize");
        }

        if ($this->maxConcurrent < 1 || $this->maxConcurrent > $semaphoreLimit) {
            throw new InvalidTaskBatchException("Concurrency must be between 1 and $semaphoreLimit");
        }

        if (!in_array($this->semaphoreDriver, SemaphoreDriver::values(), true)) {
            $allowed = implode(', ', SemaphoreDriver::values());
            throw new InvalidTaskBatchException("Invalid semaphore driver '{$this->semaphoreDriver}'. Allowed: $allowed");
        }
    }
}
