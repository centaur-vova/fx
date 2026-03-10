<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Task\Strategy;

use App\Service\Task\Strategy\DemoDelayStrategy;
use PHPUnit\Framework\TestCase;

class DemoDelayStrategyTest extends TestCase
{
    public function test_it_calculates_delay_within_expected_range(): void
    {
        $strategy = new DemoDelayStrategy();

        $iteration = 2;

        $result = $strategy($iteration);

        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThanOrEqual(5000, $result);
    }
}
