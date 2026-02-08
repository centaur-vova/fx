<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\Monitoring\SystemStats;
use App\WebSocket\ConnectionPool;
use Swoole\WebSocket\Server;

class SystemMonitor
{
    private array $lastUsage;
    private float $lastTime;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly int $workers,
    ) {
        $this->lastUsage = getrusage();
        $this->lastTime = microtime(true);
    }

    /**
     * Capture current system metrics and calculate delta
     */
    public function capture(Server $server): SystemStats
    {
        $currentUsage = getrusage();
        $currentTime = microtime(true);

        // CPU Calculation logic
        $userDelta = ($currentUsage['ru_utime.tv_sec'] + $currentUsage['ru_utime.tv_usec'] / 1000000)
            - ($this->lastUsage['ru_utime.tv_sec'] + $this->lastUsage['ru_utime.tv_usec'] / 1000000);
        $sysDelta = ($currentUsage['ru_stime.tv_sec'] + $currentUsage['ru_stime.tv_usec'] / 1000000)
            - ($this->lastUsage['ru_stime.tv_sec'] + $this->lastUsage['ru_stime.tv_usec'] / 1000000);

        $timeDelta = $currentTime - $this->lastTime;
        $cpuPercent = $timeDelta > 0 ? round((($userDelta + $sysDelta) / $timeDelta) * 100, 2) : 0;

        // Update state
        $this->lastUsage = $currentUsage;
        $this->lastTime = $currentTime;

        return new SystemStats(
            workers: $this->workers,
            connections: $this->connectionPool->count(),
            memoryMb: round(memory_get_usage() / 1024 / 1024, 2),
            cpuPercent: $cpuPercent,
        );
    }
}
