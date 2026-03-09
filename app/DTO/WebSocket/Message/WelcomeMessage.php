<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Message;

use App\Contract\Support\Arrayable;

final readonly class WelcomeMessage implements Arrayable
{
    public function __construct(
        public int $workerNum,
        public int $cpuCores,
        public int $queueCapacity,
        public string $appVersion,
    ) {
    }

    public function toArray(): array
    {
        return [
            'worker_num' => $this->workerNum,
            'cpu_cores' => $this->cpuCores,
            'queue_capacity' => $this->queueCapacity,
            'app_version' => $this->appVersion,
        ];
    }
}
