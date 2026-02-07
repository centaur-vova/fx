<?php

declare(strict_types=1);

namespace App\Contracts\Tasks;

interface TaskSemaphore
{
    public function forLimit(int $mc): SemaphorePermit;

    public function close(): void;
}
