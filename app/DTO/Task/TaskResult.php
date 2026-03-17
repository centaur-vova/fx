<?php

declare(strict_types=1);

namespace App\DTO\Task;

final readonly class TaskResult
{
    public function __construct(
        public int $taskId,
        public bool $success,
    ) {
    }
}
