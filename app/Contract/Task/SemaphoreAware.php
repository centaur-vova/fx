<?php

declare(strict_types=1);

namespace App\Contract\Task;

interface SemaphoreAware
{
    public function withSem(string $sem): self;
}
