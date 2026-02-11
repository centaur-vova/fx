<?php

declare(strict_types=1);

namespace App\DTO\WebSockets\Messages;

use App\Contracts\Support\Arrayable;

final readonly class WelcomeMessage implements Arrayable
{
    public function __construct(
        public int $workerNum,
        public int $cpuCores,
        public int $queueCapacity,
    ) {
    }

    public function toArray(): array
    {
        return [
            'worker_num' => $this->workerNum,
            'cpu_cores' => $this->cpuCores,
            'queue_capacity' => $this->queueCapacity,
        ];
    }
}
