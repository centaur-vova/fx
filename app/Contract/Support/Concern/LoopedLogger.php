<?php

declare(strict_types=1);

namespace App\Contract\Support\Concern;

trait LoopedLogger
{
    private ?float $lastDebugLog = null;
    private int $emptyIterations = 0;

    /**
     * @param array<string, mixed> $context
     */
    protected function logLoopDebug(string $message, array $context = []): void
    {
        $now = microtime(true);
        if ($this->lastDebugLog === null || ($now - $this->lastDebugLog) >= 1.0) {
            $this->logger->debug($message, $context);
            $this->lastDebugLog = $now;
        }
    }

    /**
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

    protected function logMemoryUsage(int $intervalSec = 60): void
    {
        /** @var int $lastMemLog */
        static $lastMemLog = 0;
        $now = time();
        if ($now - $lastMemLog >= $intervalSec) {
            $this->logger->info('Memory usage', [
                'memory_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            ]);
            $lastMemLog = $now;
        }
    }
}
