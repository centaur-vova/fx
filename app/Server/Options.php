<?php

declare(strict_types=1);

namespace App\Server;

use App\Contract\Cache\CacheDriver;
use App\Contract\Support\AppEnv;

/**
 * Atomic Flow Engine Options
 *
 * Immutable Data Transfer Object for system-wide configuration.
 */
readonly class Options
{
    /**
     * @param array<string, array{max_attempts: int, ttl: int}> $rateLimiters
     */
    public function __construct(
        public AppEnv $appEnv,

        // === Server ===
        public string $serverHost,
        public int $serverPort,
        public int $workerNum,
        public int $shutdownTimeoutSec,

        // === Cache ===
        public CacheDriver $cacheDriver,
        public int $cacheDefaultTtlSec,
        public int $cacheMaxSize,
        public float $cacheAutoCleanSec,
        public int $cacheValueMaxSize,

        // === NATS ===
        public string $natsHost,
        public int $natsPort,
        public string $natsToken,
        public float $natsTimeoutSec,
        public int $natsPingIntervalSec,
        public int $natsWorkerPingIntervalSec,
        public int $natsAckWaitMs,
        public string $taskQueueConsumer,
        public string $taskQueueSubject,
        public string $taskQueueStream,
        public string $broadcastSubject,

        // === Logging ===
        public string $logLevel,

        // === API ===
        public string $apiUrl,
        public string $apiAuthKey,

        // === Semaphores ===
        public int $semaphorePermitTtl,

        // === Task Engine ===
        public int $queueCapacity,
        public int $queuePrefetchBatch,
        public int $taskMaxActive,
        public int $taskMetaTtlSec,
        public int $taskMaxBatchSize,
        public int $taskSemaphoreLimit,
        public float $taskLockTimeoutSec,
        public int $taskRetryDelaySec,
        public float $taskRetryJitterFactor,
        public int $taskMaxRetries,

        // === OpenTelemetry ===
        public string $otelServiceName,

        // === Misc ===
        public array $rateLimiters,
    ) {
    }
}
