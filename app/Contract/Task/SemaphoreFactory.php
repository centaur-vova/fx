<?php

declare(strict_types=1);

namespace App\Contract\Task;

/**
 * Factory for creating semaphore instances based on the selected driver.
 */
interface SemaphoreFactory
{
    /**
     * Returns a semaphore instance for the specified driver.
     *
     * @param SemaphoreDriver $driver The driver to use
     *
     * @return TaskSemaphore The configured semaphore
     */
    public function get(SemaphoreDriver $driver): TaskSemaphore;

    /**
     * Shuts down all active semaphores.
     *
     * Releases all resources held by semaphores across all drivers.
     */
    public function shutdown(): void;
}
