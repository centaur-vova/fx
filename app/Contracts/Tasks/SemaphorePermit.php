<?php

declare(strict_types=1);

namespace App\Contracts\Tasks;

interface SemaphorePermit
{
    public function acquire(float $timeout): bool;

    public function release(): void;
}
