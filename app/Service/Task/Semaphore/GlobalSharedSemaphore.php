<?php

declare(strict_types=1);

namespace App\Service\Task\Semaphore;

use App\Contract\Task\SemaphorePermit;
use App\Contract\Task\TaskSemaphore;
use Swoole\Atomic;
use Swoole\Coroutine as Co;

/**
 * Global semaphore using Swoole Atomic in shared memory.
 * Synchronizes task limits across all worker processes.
 */
class GlobalSharedSemaphore implements TaskSemaphore
{
    /**
     * @param array<int, Atomic> $atomics
     */
    public function __construct(private array $atomics)
    {
    }

    public function forLimit(int $mc): SemaphorePermit
    {
        $atomic = $this->atomics[$mc] ?? null;

        return new readonly class ($atomic, $mc) implements SemaphorePermit {
            public function __construct(
                private ?Atomic $atomic,
                private int $limit,
            ) {
            }

            public function acquire(float $lockWaitTimeoutSec): bool
            {
                $atomic = $this->atomic;
                if (!$atomic) {
                    return true;
                }

                $start = microtime(true);

                // Poll until slot is free or timeout reached
                while (microtime(true) - $start < $lockWaitTimeoutSec) {
                    // Try to take a slot immediately
                    $current = $atomic->add(1);

                    // Check if we are within the concurrency limit
                    if ($current <= $this->limit) {
                        return true;
                    }

                    // Limit exceeded: immediately release the slot and wait
                    $atomic->sub(1);

                    // Yield execution to let other coroutines work
                    Co::sleep(0.01);
                }

                return false;
            }

            public function release(): void
            {
                $this->atomic?->sub(1);
            }
        };
    }

    public function close(): void
    {
        // Atomics are managed by Swoole Master process
    }
}
