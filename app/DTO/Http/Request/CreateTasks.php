<?php

declare(strict_types=1);

namespace App\DTO\Http\Request;

use App\Contract\Task\SemaphoreDriver;
use App\Exception\Http\InvalidTaskBatchException;

/**
 * Incoming request DTO for the task creation endpoint.
 *
 * $count = 0 triggers random batch mode (RAND).
 * $semaphoreDriver is nullable — only required when not in random mode.
 */
final readonly class CreateTasks
{
    public function __construct(
        public int $count,
        public int $maxConcurrent,
        public bool $stressMode,
        public ?SemaphoreDriver $semaphoreDriver = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $semAsString = is_string($payload['semaphore_driver'] ?? null)
            ? $payload['semaphore_driver']
            : '';
        $sem = SemaphoreDriver::tryFrom($semAsString);

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
            stressMode: (bool) ($payload['stress_mode'] ?? false),
            semaphoreDriver: $sem,
        );
    }

    public function validate(int $maxBatchSize, int $semaphoreLimit): void
    {
        if ($this->count < 0 || $this->count > $maxBatchSize) {
            throw new InvalidTaskBatchException("Count must be between 1 and $maxBatchSize");
        }

        if ($this->maxConcurrent < 1 || $this->maxConcurrent > $semaphoreLimit) {
            throw new InvalidTaskBatchException("Concurrency must be between 1 and $semaphoreLimit");
        }

        if (!$this->inRandomMode() and !$this->semaphoreDriver) {
            throw new InvalidTaskBatchException('Invalid semaphore driver');
        }
    }

    public function inRandomMode(): bool
    {
        return $this->count === 0;
    }
}
