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
            $response = $this->client->post('/semaphore/acquire', [
                'max_concurrent' => $maxConcurrent,
                'lock_wait_timeout' => $lockWaitTimeoutSec,
                'permit_ttl' => $permitTTLSec,
            ]);

            $uid = is_numeric($response['uid'] ?? null) ? (int) $response['uid'] : 0;

            return $uid < 0 ? null : $uid;
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
