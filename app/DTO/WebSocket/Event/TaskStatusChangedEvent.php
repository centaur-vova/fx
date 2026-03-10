<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Event;

use App\DTO\WebSocket\Message\TaskStatusUpdate;

final readonly class TaskStatusChangedEvent
{
    public function __construct(public TaskStatusUpdate $update)
    {
    }
}
