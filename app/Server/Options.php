<?php

declare(strict_types=1);

namespace App\Server;

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
        // ========== Server Infrastructure ==========
        public string $serverHost,
        public int $serverPort,
        public int $workerNum,
        public int $dispatchMode,
        public int $socketBufferMb,

        // ========== App Cache ==========
        public string $cacheStorageDriver,
        public int $cacheDefaultTtlSec,
        public int $cacheMaxSize,
        public int $cacheAutoCleanSec,
        public int $cacheValueMaxSize,

        // ========== Nats ==========
        public string $natsHost,
        public int $natsPort,
        public string $natsToken,
        public float $natsTimeoutSec,
        public int $natsPingIntervalSec,
        public int $natsWorkerPingIntervalSec,
        public int $natsAckWaitMs,

        // ========== Logging ==========
        public string $logLevel,

        // ========== API ==========
        public string $apiUrl,
        public string $apiToken,

        // ========== Semaphores ==========
        public int $semaphorePermitTtl, // for `api` semaphoreDriver only

        // ========== Queue ==========
        public int $queueCapacity,
        public int $queuePrefetchBatch,

        // ========== Task Meta ==========
        public int $taskMaxActive,
        public int $taskMetaTtlSec,

        // ========== Task Engine ==========
        public int $taskMaxBatchSize,
        public int $taskSemaphoreLimit,
        public float $taskLockTimeoutSec,
        public int $taskRetryDelaySec,
        public int $taskMaxRetries,

        // ========== Real-time ==========
        public int $metricsIntervalMs,
        public int $shutdownTimeoutSec,

        // ========== Messaging consumer (group) name ==========
        public string $taskQueueConsumer,

        // ========== Messaging subjects/streams ==========
        public string $broadcastSubject,
        public string $taskQueueSubject,
        public string $taskQueueStream,

        // ========== Misc ==========
        public array $rateLimiters,
    ) {
    }
}
