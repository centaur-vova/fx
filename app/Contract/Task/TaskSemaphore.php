<?php

declare(strict_types=1);

namespace App\Contract\Task;

/**
 * Semaphore for controlling concurrent task execution per concurrency limit.
 */
interface TaskSemaphore
{
    /**
     * Returns a permit for the specified concurrency limit.
     *
     * @param int $mc Maximum concurrency (1-255)
     *
     * @return SemaphorePermit A permit for the given concurrency level
     */
    public function forLimit(int $mc): SemaphorePermit;

    /**
     * Shuts down the semaphore and releases all resources.
     */
    public function shutdown(): void;
}
