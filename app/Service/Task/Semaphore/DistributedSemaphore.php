<?php

declare(strict_types=1);

namespace App\Service\Task\Semaphore;

use App\Contract\Task\SemaphorePermit;
use App\Contract\Task\TaskSemaphore;
use App\Service\Api\CoreApi;

/**
 * Distributed semaphore using a Go microservice via HTTP.
 * Synchronizes task limits across all worker processes, possibly across multiple servers.
 *
 * Acquires and releases slots by communicating with a remote Go semaphore service
 * (see: go/internal/api/semaphore) over HTTP, using non-blocking coroutine-aware calls.
 */
final readonly class DistributedSemaphore implements TaskSemaphore
{
    public function __construct(private CoreApi $api, private int $semaphorePermitTtl)
    {
    }

    public function forLimit(int $mc): SemaphorePermit
    {
        $api = $this->api;
        $semaphorePermitTtl = $this->semaphorePermitTtl;

        return new class ($api, $mc, $semaphorePermitTtl) implements SemaphorePermit {
            private ?string $permitUid = null;

            public function __construct(
                private readonly CoreApi $api,
                private readonly int $limit,
                private readonly int $semaphorePermitTtl,
            ) {
            }

            public function acquire(float $lockWaitTimeoutSec): bool
            {
                $permitUid = $this->api->acquireSemaphore($this->limit, (int) $lockWaitTimeoutSec, $this->semaphorePermitTtl);

                if (empty($permitUid)) {
                    return false;
                }

                $this->permitUid = $permitUid;
                return true;
            }

            public function release(): void
            {
                if ($this->permitUid !== null) {
                    $this->api->releaseSemaphore($this->permitUid);
                }
            }
        };
    }

    public function shutdown(): void
    {
        // do nothing
    }
}
