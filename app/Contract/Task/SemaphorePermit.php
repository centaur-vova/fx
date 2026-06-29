<?php

declare(strict_types=1);

namespace App\Contract\Task;

/**
 * Represents a permit acquired from a semaphore.
 *
 * Provides acquire/release mechanism for concurrency control.
 */
interface SemaphorePermit
{
    /**
     * Attempts to acquire the permit within the given timeout.
     *
     * @param float $lockWaitTimeoutSec Maximum time to wait in seconds
     *
     * @return bool True if acquired, false otherwise
     */
    public function acquire(float $lockWaitTimeoutSec): bool;

    /**
     * Releases the acquired permit back to the semaphore.
     */
    public function release(): void;
}
