<?php

declare(strict_types=1);

namespace App\Contracts\Monitoring;

interface TaskCounter
{
    public function add(int $addValue = 1): int;

    public function sub(int $subValue = 1): int;

    public function increment(): void;

    public function decrement(): void;

    public function get(): int;
}
