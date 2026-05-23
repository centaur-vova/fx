<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\DTO\Balancer\Health;
use Psr\Log\LoggerInterface;

final readonly class BalancerApi
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

    /**
     * Return balancer's health
     */
    public function health(): ?Health
    {
        try {
            /** @var null|array{
             *  up?: int,
             *  down?: int,
             *  total_requests?: int,
             *  total_errors?: int,
             *  uptime_seconds?: int,
             *  instances?: array<
             *      array{
             *          hash: string,
             *          alive: bool,
             *          cb_state: string,
             *          requests: int,
             *          errors: int
             *      }
             *  >} $response */
            $response = $this->client->get('/health');
            if ($response !== null) {
                return Health::fromArray($response);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Balancer health request failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Revives a previously force-unalived API instance by its hash.
     *
     * Clears the forced unalived flag and marks the instance as alive,
     * allowing it to receive requests again through the balancer.
     *
     * @param string $hash Unique identifier of the API instance (e.g., "35192206")
     * @return bool True if the instance was successfully revived, false otherwise
     */
    public function reviveInstance(string $hash): bool
    {
        try {
            $this->client->post('/instance/revive', ['hash' => $hash]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Balancer revive instance failed', ['hash' => $hash, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Forcefully marks an API instance as dead/unavailable by its hash.
     *
     * This temporarily flags the instance as not alive, preventing any new requests
     * from being routed to it. The instance will automatically become alive again
     * on its next registration heartbeat (typically 20 seconds).
     *
     * This does NOT affect the circuit breaker state, only the instance's alive status.
     *
     * @param string $hash Unique identifier of the API instance (e.g., "35192206")
     * @return bool True if the instance was successfully marked as dead, false otherwise
     */
    public function forceUnaliveInstance(string $hash): bool
    {
        try {
            $this->client->post('/instance/unalive', ['hash' => $hash]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Balancer force request failed', ['hash' => $hash, 'error' => $e->getMessage()]);
        }

        return false;
    }
}
