<?php

declare(strict_types=1);

namespace App\Server\Http\Request;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\Exception\Http\InvalidTaskBatchException;

/**
 * Incoming request DTO for the task creation endpoint.
 *
 * When $count is 0 (RAND mode), all other parameters are ignored — random batches are generated instead.
 * When $count > 0, $taskMode and $semaphoreDriver are required.
 *
 * $semaphoreDriver is nullable — only required when not in random mode.
 */
final readonly class CreateTasks
{
    public function __construct(
        public int $count,
        public int $maxConcurrent,
        public ?TaskMode $taskMode,
        public ?SemaphoreDriver $semaphoreDriver,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        // semaphore_driver literal to SemaphoreDriver
        $semAsString = is_string($payload['semaphore_driver'] ?? null)
            ? $payload['semaphore_driver']
            : '';
        $sem = SemaphoreDriver::tryFrom($semAsString);

        // task_mode literal to TaskMode
        $taskModeAsString = is_string($payload['task_mode'] ?? null)
            ? $payload['task_mode']
            : '';
        $taskMode = TaskMode::tryFrom($taskModeAsString);

        /** @var array{
         *      count?: int|string,
         *      max_concurrent?: int|string,
         *      semaphore_driver?: string,
         *      task_mode?: string
         *  } $payload
         */
        return new self(
            count: (int) ($payload['count'] ?? 1),
            maxConcurrent: (int) ($payload['max_concurrent'] ?? 2),
            taskMode: $taskMode,
            semaphoreDriver: $sem,
        );
    }

    public function validate(int $maxBatchSize, int $semaphoreLimit): void
    {
        if ($this->inRandomMode()) {
            return;
        }

        /* --- Further checks for non-random mode --- */
        if ($this->count < 1 || $this->count > $maxBatchSize) {
            throw new InvalidTaskBatchException("Count must be between 1 and $maxBatchSize");
        }

        if ($this->maxConcurrent < 1 || $this->maxConcurrent > $semaphoreLimit) {
            throw new InvalidTaskBatchException("Concurrency must be between 1 and $semaphoreLimit");
        }

        if (!$this->semaphoreDriver) {
            throw new InvalidTaskBatchException('Invalid semaphore driver');
        }

        if (!$this->taskMode) {
            throw new InvalidTaskBatchException('Invalid task mode');
        }
    }

    public function inRandomMode(): bool
    {
        return $this->count === 0;
    }
}
