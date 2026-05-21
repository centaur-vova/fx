<?php

declare(strict_types=1);

namespace App\Service\Api;

use Psr\Log\LoggerInterface;

class SemaphoreApi
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return string - permit UID
     */
    public function acquire(int $maxConcurrent, int $lockWaitTimeoutSec = 5, int $permitTTLSec = 10): ?string
    {
        try {
            /** @var array{uid?: string} $response */
            $response = $this->client->post('/semaphore/acquire', [
                'max_concurrent' => $maxConcurrent,
                'lock_wait_timeout' => $lockWaitTimeoutSec,
                'permit_ttl' => $permitTTLSec,
            ]);

            $uid = $response['uid'] ?? null;

            return $uid;
        } catch (\Throwable) {
            return null;
        }
    }

    public function release(string $uid): bool
    {
        $this->logger->debug('API semaphore release', ['uid' => $uid]);

        try {
            $this->client->post('/semaphore/release', [
                'uid' => $uid,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->debug('API semaphore release error', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
