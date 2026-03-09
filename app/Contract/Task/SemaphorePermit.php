<?php

declare(strict_types=1);

namespace App\Contract\Task;

interface SemaphorePermit
{
    public function acquire(float $timeout): bool;

    public function release(): void;
}
