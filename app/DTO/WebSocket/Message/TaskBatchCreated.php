<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreDriver;
use JsonSerializable;

final readonly class TaskBatchCreated implements JsonSerializable
{
    public function __construct(
        public int $count,
        public int $mc,
        public string $mode,
        public SemaphoreDriver $sem,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'count' => $this->count,
            'mc' => $this->mc,
            'mode' => $this->mode,
            'sem' => $this->sem->value,
        ];
    }
}
