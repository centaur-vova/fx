<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tasks\Strategies;

use App\Services\Tasks\Strategies\DemoDelayStrategy;
use PHPUnit\Framework\TestCase;

class DemoDelayStrategyTest extends TestCase
{
    public function test_it_calculates_delay_within_expected_range(): void
    {
        $strategy = new DemoDelayStrategy();

        $iteration = 2;
        $baseDelay = 1; // 1 second (1000ms)

        $result = $strategy($iteration, $baseDelay);

        /**
         * Logic validation:
         * Min: 1000 (base) + 0 (min jitter) = 1000ms
         * Max: 1000 (base) + 5000 (max jitter) = 6000ms
         */
        $this->assertGreaterThanOrEqual(1000, $result);
        $this->assertLessThanOrEqual(6000, $result);
    }
}
