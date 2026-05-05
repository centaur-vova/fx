<?php

declare(strict_types=1);

namespace App\Service\Api;

class SemaphoreApi
{
    public function __construct(
        private readonly ApiClient $client,
    ) {
    }

    /**
     * @return int - permit UID
     */
    public function acquire(int $maxConcurrent, int $lockWaitTimeoutSec = 5, int $permitTTLSec = 10): ?int
    {
        try {
            /** @var array{uid?: int|string|float} $response */
            $response = $this->client->post('/semaphore/acquire', [
                'max_concurrent' => $maxConcurrent,
                'lock_wait_timeout' => $lockWaitTimeoutSec,
                'permit_ttl' => $permitTTLSec,
            ]);

            $uid = isset($response['uid']) ? (int) $response['uid'] : 0;
            return $uid > 0 ? $uid : null; // Return null if UID is 0 or missing
        } catch (\Throwable) {
            return null;
        }
    }

    public function release(int $uid): bool
    {
        try {
            $this->client->post('/semaphore/release', [
                'uid' => $uid,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
