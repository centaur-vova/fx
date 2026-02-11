<?php

declare(strict_types=1);

namespace App\Services\Tasks\Semaphores;

use App\Contracts\Tasks\SemaphorePermit;
use App\Contracts\Tasks\TaskSemaphore;
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

            public function acquire(float $timeout): bool
            {
                if (!$this->atomic) {
                    return true;
                }

                $start = microtime(true);
                // Poll until slot is free or timeout reached
                while (microtime(true) - $start < $timeout) {
                    $current = $this->atomic->get();

                    if ($current < $this->limit) {
                        // CAS
                        if ($this->atomic->cmpset($current, $current + 1)) {
                            // Success
                            return true;
                        }

                        // Unlucky this time, try again right away
                        continue;
                    }
                    // Yield control to other coroutines
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
