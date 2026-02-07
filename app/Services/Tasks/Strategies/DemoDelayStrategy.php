<?php

declare(strict_types=1);

namespace App\Services\Tasks\Strategies;

use App\Contracts\Tasks\TaskDelayStrategy;

class DemoDelayStrategy implements TaskDelayStrategy
{
    public function __invoke(int $iteration, int $baseDelay): int
    {
        $stagger = $iteration * 150;
        $jitter = mt_rand(0, 3000);

        // Base delay (ms)
        $base = $baseDelay * 1000;

        return $base + $stagger + $jitter;
    }
}
