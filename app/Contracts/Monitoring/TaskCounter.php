<?php

declare(strict_types=1);

namespace App\Contracts\Monitoring;

interface TaskCounter
{
    public function increment(): void;

    public function decrement(): void;

    public function get(): int;
}
