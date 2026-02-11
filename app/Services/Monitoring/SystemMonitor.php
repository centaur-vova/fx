<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\WebSockets\Messages\SystemStats;
use App\WebSocket\ConnectionPool;

class SystemMonitor
{
    private array $lastUsage;
    private float $lastTime;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly int $cpuCores,
    ) {
        $this->lastUsage = getrusage();
        $this->lastTime = microtime(true);
    }

    /**
     * Capture current system metrics and calculate delta
     */
    public function capture(): SystemStats
    {
        $currentUsage = getrusage();
        $currentTime = microtime(true);

        // CPU Calculation logic
        $userDelta = ($currentUsage['ru_utime.tv_sec'] + $currentUsage['ru_utime.tv_usec'] / 1000000)
            - ($this->lastUsage['ru_utime.tv_sec'] + $this->lastUsage['ru_utime.tv_usec'] / 1000000);
        $sysDelta = ($currentUsage['ru_stime.tv_sec'] + $currentUsage['ru_stime.tv_usec'] / 1000000)
            - ($this->lastUsage['ru_stime.tv_sec'] + $this->lastUsage['ru_stime.tv_usec'] / 1000000);

        $timeDelta = $currentTime - $this->lastTime;
        $totalCpu = ($userDelta + $sysDelta) / $timeDelta;
        $cpuUsage = $timeDelta > 0 ? round(($totalCpu / max(1, $this->cpuCores)) * 100, 2) : 0;

        // Update state
        $this->lastUsage = $currentUsage;
        $this->lastTime = $currentTime;

        $connections = $this->connectionPool->count();
        $memoryMb = round(memory_get_usage() / 1024 / 1024, 2);

        return new SystemStats(
            cpuUsage: $cpuUsage,
            connections: $connections,
            memoryMb: $memoryMb,
        );
    }
}
