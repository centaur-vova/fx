<?php

declare(strict_types=1);

namespace App\DTO\Monitoring;

use App\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * System stats snapshot
 */
final readonly class SystemStats implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $connections,
        public float $memoryMb,
        public float $cpuUsage,
        public int $workers,
        public int $cpuCores,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'connections' => $this->connections,
            'memory_mb' => $this->memoryMb,
            'cpu_usage' => $this->cpuUsage,
            'workers' => $this->workers,
            'cpu_cores' => $this->cpuCores,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
