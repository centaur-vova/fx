<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Message;

use App\DTO\WebSocket\Concern\InteractsWithWebSocket;
use JsonSerializable;

final readonly class TaskBatchCreated implements JsonSerializable
{
    use InteractsWithWebSocket;

    public function __construct(
        public int $count,
        public int $mc,
        public string $mode,
    ) {
    }
}
