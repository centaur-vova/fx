<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Contracts\Monitoring\TaskCounter;
use App\DTO\Monitoring\Metrics;
use App\Services\Tasks\TaskService;
use App\WebSocket\ConnectionPool;
use Swoole\WebSocket\Server;

class SystemMonitor
{
    private array $lastUsage;
    private float $lastTime;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly TaskCounter $taskCounter,
        private readonly TaskService $taskService,
    ) {
        $this->lastUsage = getrusage();
        $this->lastTime = microtime(true);
    }

    /**
     * Capture current system metrics and calculate delta
     */
    public function capture(Server $server): Metrics
    {
        $currentUsage = getrusage();
        $currentTime = microtime(true);

        // CPU Calculation logic
        $userDelta = ($currentUsage['ru_utime.tv_sec'] + $currentUsage['ru_utime.tv_usec'] / 1000000)
            - ($this->lastUsage['ru_utime.tv_sec'] + $this->lastUsage['ru_utime.tv_usec'] / 1000000);
        $sysDelta = ($currentUsage['ru_stime.tv_sec'] + $currentUsage['ru_stime.tv_usec'] / 1000000)
            - ($this->lastUsage['ru_stime.tv_sec'] + $this->lastUsage['ru_stime.tv_usec'] / 1000000);

        $timeDelta = $currentTime - $this->lastTime;
        $cpuUsage = $timeDelta > 0 ? round((($userDelta + $sysDelta) / $timeDelta) * 100, 2) : 0;

        // Update state
        $this->lastUsage = $currentUsage;
        $this->lastTime = $currentTime;

        return new Metrics(
            workerId: $server->worker_id,
            memory: round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
            connections: $this->connectionPool->count(),
            cpu: $cpuUsage . '%',
            tasks: $this->taskCounter->get(),
            queue: $this->taskService->getQueueStats(),
        );
    }
}
