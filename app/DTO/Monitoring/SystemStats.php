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
        public int $workers,
        public float $memoryMb,
        public float $cpuPercent,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'connections' => $this->connections,
            'workers' => $this->workers,
            'memory_mb' => $this->memoryMb,
            'cpu_percent' => $this->cpuPercent,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
