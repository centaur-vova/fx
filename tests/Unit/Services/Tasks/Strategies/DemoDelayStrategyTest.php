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
        $baseDelay = 1; // 1 second

        $result = $strategy($iteration, $baseDelay);

        $this->assertGreaterThanOrEqual(1300, $result);
        $this->assertLessThanOrEqual(1600, $result);
    }

    public function test_it_increases_delay_with_iterations(): void
    {
        $strategy = new DemoDelayStrategy();

        $delay1 = $strategy(1, 0); // 150 + jitter(0-300) = 150-450
        $delay10 = $strategy(10, 0); // 1500 + jitter(0-300) = 1500-1800

        $this->assertLessThan($delay10, $delay1);
    }
}
