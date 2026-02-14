<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\WebSockets\Messages\SystemStats;
use App\WebSocket\ConnectionPool;

class SystemMonitor
{
    /** @var array<int, int>|null */
    private ?array $lastCpuStats = null;

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }

    /**
     * Capture current system metrics and calculate delta
     */
    public function capture(): SystemStats
    {
        $cpuUsage = $this->getGlobalCpuUsage();
        $connections = $this->connectionPool->count();
        $memoryMb = round(memory_get_usage() / 1024 / 1024, 2);

        return new SystemStats(
            cpuUsage: $cpuUsage,
            connections: $connections,
            memoryMb: $memoryMb,
        );
    }

    private function getGlobalCpuUsage(): float
    {
        // Read first line of /proc/stat
        $stat = file_get_contents('/proc/stat');
        if (!$stat) {
            return 0.0;
        }

        $lines = explode("\n", $stat);
        $cpuLine = str_replace("cpu  ", "", $lines[0]);
        $values = array_map('intval', explode(" ", $cpuLine));

        // Array map: 0:user, 1:nice, 2:system, 3:idle, 4:iowait, 5:irq, 6:softirq
        $idle = $values[3] + $values[4];
        $active = $values[0] + $values[1] + $values[2] + $values[5] + $values[6];
        $total = $idle + $active;

        if ($this->lastCpuStats === null) {
            $this->lastCpuStats = ['total' => $total, 'active' => $active];
            return 0.0;
        }

        // Calculate Delta
        $diffTotal = $total - $this->lastCpuStats['total'];
        $diffActive = $active - $this->lastCpuStats['active'];

        // Update state for next tick
        $this->lastCpuStats = ['total' => $total, 'active' => $active];

        if ($diffTotal <= 0) {
            return 0.0;
        }

        // Percentage of total time spent in active states
        return round(($diffActive / $diffTotal) * 100, 2);
    }

}
