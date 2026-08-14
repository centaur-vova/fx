<?php

declare(strict_types=1);

namespace App\Contract\Support\Concern;

/**
 * Provides throttled logging for long-running loops.
 *
 * Use this trait to avoid flooding logs with repetitive debug messages.
 */
trait LoopedLogger
{
    private ?float $lastDebugLogAt = null;
    private int $emptyIterations = 0;

    /**
     * Logs a debug message at most once per second.
     *
     * @param array<string, mixed> $context
     */
    protected function logLoopDebug(string $message, array $context = []): void
    {
        $now = microtime(true);
        if ($this->lastDebugLogAt === null || ($now - $this->lastDebugLogAt) >= 1.0) {
            $this->logger->debug($message, $context);
            $this->lastDebugLogAt = $now;
        }
    }

    /**
     * Logs a debug message every N empty iterations.
     *
     * @param array<string, mixed> $context
     */
    protected function logEmptyIteration(string $message, array $context = [], int $interval = 10): void
    {
        $this->emptyIterations++;
        if ($this->emptyIterations >= $interval) {
            $this->logger->debug($message, $context);
            $this->emptyIterations = 0;
        }
    }

    /**
     * Logs memory usage at a fixed interval (default: every 10 minutes).
     */
    protected function logMemoryUsage(int $intervalSec = 600): void
    {
        /** @var int $lastMemLogAt */
        static $lastMemLogAt = 0;
        $now = time();
        if ($now - $lastMemLogAt >= $intervalSec) {
            $this->logger->info('Memory usage', [
                'memory_mb' => $this->bytesToMb(memory_get_usage()),
                'peak_mb' => $this->bytesToMb(memory_get_peak_usage()),
            ]);
            $lastMemLogAt = $now;
        }
    }

    private function bytesToMb(int $bytes): float
    {
        return round($bytes / 1024 ** 2, 2);
    }
}
