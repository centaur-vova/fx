<?php

declare(strict_types=1);

namespace Tests\Unit\Service\RateLimiter;

use App\Service\RateLimiter\RateLimiterConfig;
use PHPUnit\Framework\TestCase;

class RateLimiterConfigTest extends TestCase
{
    public function testValidConfig(): void
    {
        $config = new RateLimiterConfig([
            'test' => ['max_attempts' => 5, 'ttl' => 60],
        ]);

        $this->assertTrue($config->has('test'));
        $this->assertSame(5, $config->getMaxAttempts('test'));
        $this->assertSame(60, $config->getTtl('test'));
        $this->assertFalse($config->has('unknown'));
        $this->assertSame(0, $config->getMaxAttempts('unknown'));
    }

    public function testMissingValuesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimiterConfig(['invalid' => []]);
    }

    public function testZeroValuesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimiterConfig(['zero' => ['max_attempts' => 0, 'ttl' => 60]]);
    }
}
