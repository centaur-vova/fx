<?php

declare(strict_types=1);

namespace App\Server\Http\Request;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\Exception\Http\InvalidTaskBatchException;

/**
 * Incoming request DTO for the task creation endpoint.
 */
final readonly class CreateTasks
{
    public function __construct(
        public int $count,
        public int $maxConcurrent,
        public TaskMode $taskMode,
        public SemaphoreDriver $semaphoreDriver,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $driverValue = is_scalar($payload['semaphore_driver'] ?? null) ? (string) $payload['semaphore_driver'] : '';
        $modeValue = is_scalar($payload['task_mode'] ?? null) ? (string) $payload['task_mode'] : '';

        $sem = SemaphoreDriver::tryFrom($driverValue);
        $taskMode = TaskMode::tryFrom($modeValue);

        if ($sem === null || $taskMode === null) {
            throw new InvalidTaskBatchException('Invalid semaphore driver or task mode');
        }

        /** @var int|string $count */
        $count = $payload['count'] ?? 0;
        /** @var int|string $maxConcurrent */
        $maxConcurrent = $payload['max_concurrent'] ?? 0;

        return new self(
            count: (int) $count,
            maxConcurrent: (int) $maxConcurrent,
            taskMode: $taskMode,
            semaphoreDriver: $sem,
        );
    }

    /**
     * @throws InvalidTaskBatchException
     */
    public function validate(int $maxBatchSize, int $semaphoreLimit): void
    {
        if ($this->count < 1 || $this->count > $maxBatchSize) {
            throw new InvalidTaskBatchException("Count must be between 1 and {$maxBatchSize}");
        }

        if ($this->maxConcurrent < 1 || $this->maxConcurrent > $semaphoreLimit) {
            throw new InvalidTaskBatchException("Concurrency must be between 1 and {$semaphoreLimit}");
        }
    }
}
