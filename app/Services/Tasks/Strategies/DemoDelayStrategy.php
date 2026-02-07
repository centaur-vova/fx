<?php

declare(strict_types=1);

namespace App\Services\Tasks\Strategies;

use App\Contracts\Tasks\TaskDelayStrategy;

class DemoDelayStrategy implements TaskDelayStrategy
{
    public function __invoke(int $iteration, int $baseDelay): int
    {
        $jitter = mt_rand(0, 5000);

        // Base delay (ms)
        $base = $baseDelay * 1000;

        return $base + $jitter;
    }
}
