<?php

declare(strict_types=1);

namespace App\Service\RateLimiter;

use App\Domain\Cache\Contract\CacheStorage;
use Psr\Log\LoggerInterface;

class RateLimiterService
{
    public function __construct(
        private readonly CacheStorage $storage,
        private readonly RateLimiterConfig $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Check if the request is allowed by the rate limiter.
     *
     * If the storage is full (e.g., Swoole table limit reached), `set()` may fail silently.
     * In that case, the method will still return `true`, effectively disabling the limiter.
     * This is a deliberate fallback to avoid blocking legitimate requests when the table is full.
     * The situation should be logged and resolved by increasing table size or reducing TTL.
     */
    public function checkLimit(string $limiterName, string $key): bool
    {
        if (!$this->config->has($limiterName)) {
            $this->logger?->warning('Rate limiter config not found', ['limiter' => $limiterName]);
            return true;
        }

        $ttl = $this->config->getTtl($limiterName);
        $maxAttempts = $this->config->getMaxAttempts($limiterName);

        $this->logger?->debug('Rate limiter check', [
            'limiter' => $limiterName,
            'key' => $key,
            'ttl' => $ttl,
            'max' => $maxAttempts,
        ]);

        if ($ttl <= 0 || $maxAttempts <= 0) {
            $this->logger?->warning('Rate limiter misconfigured', [
                'limiter' => $limiterName,
                'ttl' => $ttl,
                'maxAttempts' => $maxAttempts,
            ]);
            return true; // skip rate limiter check
        }

        $storageKey = "{$limiterName}:{$key}";
        $current = $this->storage->get($storageKey);

        $this->logger?->debug('Rate limiter storage get', [
                'limiter' => $limiterName,
                'storageKey' => $storageKey,
                'current' => $current,
        ]);

        if ($current === null) {
            $this->storage->set($storageKey, '1', $ttl);
            return true;
        }

        $attempts = (int) $current;
        if ($attempts >= $maxAttempts) {
            $this->logger?->info('Rate limit exceeded', ['key' => $storageKey, 'attempts' => $attempts]);
            return false;
        }

        $success = $this->storage->set($storageKey, (string) ($attempts + 1), $ttl);

        $this->logger?->debug('Rate limiter storage set', [
            'limiter' => $limiterName,
            'storageKey' => $storageKey,
            'newValue' => (string) ($attempts + 1),
            'success' => $success,
        ]);

        if (!$success) {
            $this->logger?->warning('Rate limiter: failed to store', ['key' => $storageKey]);
        }
        return $success;
    }
}
