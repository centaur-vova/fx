<?php

declare(strict_types=1);

namespace App\Service\Api;

use Psr\Log\LoggerInterface;

final readonly class CoreApi
{
    public function __construct(
        private ApiClient $client,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Acquire a semaphore permit
     *
     * @return string - permit UID
     */
    public function acquireSemaphore(int $maxConcurrent, int $lockWaitTimeoutSec = 5, int $permitTTLSec = 10): ?string
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

    /**
     * Release the semaphore permit
     */
    public function releaseSemaphore(string $uid): bool
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
