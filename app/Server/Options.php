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
    public function __construct(
        // App version
        public string $appVersion,

        // Server Infrastructure
        public string $serverHost,
        public int $serverPort,
        public int $workerNum,
        public int $dispatchMode,
        public int $socketBufferMb,

        // Nats
        public string $natsHost,
        public int $natsPort,
        public string $natsToken,
        public float $natsTimeoutSec,
        public int $natsPingIntervalSec,
        public int $natsAckWaitMs,

        // Logging
        public string $logLevel,

        // Queue
        public int $queueCapacity,
        public int $queuePrefetchBatch,

        // Task Meta
        public int $taskMetaCacheSize,
        public int $taskMetaTtlSec,

        // Task Engine
        public int $taskMaxBatchSize,
        public int $taskSemaphoreLimit,
        public float $taskLockTimeoutSec,
        public int $taskRetryDelaySec,
        public int $taskMaxRetries,
        public int $stressMinTaskNum,

        // Real-time
        public int $metricsIntervalMs,
        public int $shutdownTimeoutSec,

        // Messaging consumer (group) name
        public string $taskQueueConsumer,

        // Messaging subjects/streams
        public string $broadcastSubject,
        public string $taskQueueSubject,
        public string $taskQueueStream,
    ) {
    }
}
