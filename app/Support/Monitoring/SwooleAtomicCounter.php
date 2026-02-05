<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use App\Contracts\Monitoring\TaskCounter;

class SwooleAtomicCounter implements TaskCounter
{
    public function __construct(private \Swoole\Atomic $atomic)
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
}
