<?php

declare(strict_types=1);

namespace App\DTO\Monitoring;

use App\DTO\Tasks\QueueStats;
use JsonSerializable;

/**
 * System performance metrics snapshot
 */
final readonly class Metrics implements JsonSerializable
{
    public function __construct(
        public int $workerId,
        public string $memory,
        public int $connections,
        public string $cpu,
        public int $tasks,
        public QueueStats $queue,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'worker' => $this->workerId,
            'memory' => $this->memory,
            'connections' => $this->connections,
            'cpu' => $this->cpu,
            'tasks' => $this->tasks,
            'queue' => $this->queue->jsonSerialize(),
        ];
    }
}
