<?php

declare(strict_types=1);

namespace App\DTO\WebSockets\Messages;

use App\Contracts\Support\Arrayable;

/**
 * Metrics aggregate
 */
final readonly class Metrics implements Arrayable
{
    public function __construct(
        public int $taskNum,
        public int $connections,
        public float $memoryMb,
        public float $cpuUsage,
    ) {
    }

    public function toArray(): array
    {
        return [
            'task_num' => $this->taskNum,
            'connections' => $this->connections,
            'memory_mb' => $this->memoryMb,
            'cpu_usage' => $this->cpuUsage,
        ];
    }
}
