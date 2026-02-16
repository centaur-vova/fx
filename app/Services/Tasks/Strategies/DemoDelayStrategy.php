<?php

declare(strict_types=1);

namespace App\Services\Tasks\Strategies;

use App\Contracts\Tasks\TaskDelayStrategy;

class DemoDelayStrategy implements TaskDelayStrategy
{
    public function __invoke(int $iteration): int
    {
        // Minimum 1ms for Swoole Timer compatibility
        if ($iteration === 0) {
            return 1;
        }

        $jitter = mt_rand(0, 5000);

        return max(1, $jitter);
    }
}
