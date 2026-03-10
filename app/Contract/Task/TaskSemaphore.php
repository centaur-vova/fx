<?php

declare(strict_types=1);

namespace App\Contract\Task;

interface TaskSemaphore
{
    public function forLimit(int $mc): SemaphorePermit;

    public function close(): void;
}
