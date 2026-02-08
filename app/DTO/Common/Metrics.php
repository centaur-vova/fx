<?php

declare(strict_types=1);

namespace App\DTO\Common;

use App\Contracts\Support\Arrayable;
use App\DTO\Monitoring\SystemStats;
use App\DTO\Tasks\QueueStats;

/**
 * Metrics aggregate
 */
final readonly class Metrics implements Arrayable
{
    public function __construct(
        public QueueStats $queue,
        public SystemStats $system,
    ) {
    }

    public function toArray(): array
    {
        return [
            'queue' => $this->queue->toArray(),
            'system' => $this->system->toArray(),
        ];
    }
}
