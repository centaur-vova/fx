<?php

declare(strict_types=1);

namespace App\Server;

use App\DTO\WebSockets\Messages\WelcomeMessage;

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

        // Logging
        public string $logLevel,

        // Shared Memory & Queues
        public int $wsTableSize,
        public int $queueCapacity,

        // Task Engine
        public int $workerConcurrency,
        public int $taskSemaphoreLimit,
        public float $taskLockTimeoutSec,
        public int $taskRetryDelaySec,
        public int $taskMaxRetries,
        public int $stressMinTaskNum,

        // Real-time
        public int $metricsIntervalMs,
        public int $shutdownTimeoutSec,

        // Hardware
        public int $cpuCores,

        // DTOs
        public WelcomeMessage $welcomeMessage,
    ) {
    }
}
