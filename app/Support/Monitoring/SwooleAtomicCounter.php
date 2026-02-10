<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use App\Contracts\Monitoring\TaskCounter;
use Swoole\Atomic;

class SwooleAtomicCounter implements TaskCounter
{
    public function __construct(private readonly Atomic $atomic)
    {
    }

    public function increment(): void
    {
        $this->atomic->add(1);
    }

    public function decrement(): void
    {
        $this->atomic->sub(1);
    }

    public function get(): int
    {
        return $this->atomic->get();
    }

    public function add(int $addValue = 1): int
    {
        return $this->atomic->add($addValue);
    }

    public function sub(int $subValue = 1): int
    {
        return $this->atomic->sub($subValue);
    }
}
