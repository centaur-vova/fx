<?php

declare(strict_types=1);

namespace App\Contract\Task;

interface SemaphoreFactory
{
    public function get(SemaphoreDriver $driver): TaskSemaphore;

    /**
     * Shutdown/terminate/release all semaphores
     */
    public function shutdown(): void;
}
