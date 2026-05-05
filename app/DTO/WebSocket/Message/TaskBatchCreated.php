<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreAware;
use App\Contract\Task\SemaphoreDriver;
use App\DTO\WebSocket\Concern\InteractsWithWebSocket;
use JsonSerializable;

final readonly class TaskBatchCreated implements JsonSerializable, SemaphoreAware
{
    use InteractsWithWebSocket;

    public function __construct(
        public int $count,
        public int $mc,
        public string $mode,
        public string $sem = SemaphoreDriver::SHARED->value,
    ) {
    }

    public function withSem(string $sem): self
    {
        return new self(
            count: $this->count,
            mc: $this->mc,
            mode: $this->mode,
            sem: $sem,
        );
    }
}
