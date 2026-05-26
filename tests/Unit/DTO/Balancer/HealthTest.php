<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Balancer;

use App\DTO\Balancer\ApiInstanceHealth;
use App\DTO\Balancer\Health;
use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'up' => 3,
            'down' => 1,
            'total_requests' => 12345,
            'total_errors' => 42,
            'uptime_seconds' => 3600,
            'instances' => [
                [
                    'hash' => 'abc',
                    'alive' => true,
                    'cb_state' => 'closed',
                    'requests' => 1000,
                    'errors' => 0,
                ],
                [
                    'hash' => 'def',
                    'alive' => false,
                    'cb_state' => 'open',
                    'requests' => 500,
                    'errors' => 10,
                ],
            ],
        ];

        $health = Health::fromArray($data);

        $this->assertSame(3, $health->up);
        $this->assertSame(1, $health->down);
        $this->assertSame(12345, $health->totalRequests);
        $this->assertSame(42, $health->totalErrors);
        $this->assertSame(3600, $health->uptimeSeconds);
        $this->assertCount(2, $health->instances);
        $this->assertInstanceOf(ApiInstanceHealth::class, $health->instances[0]);
    }

    public function testFromArrayWithMissingData(): void
    {
        $data = [];

        $health = Health::fromArray($data);

        $this->assertSame(0, $health->up);
        $this->assertSame(0, $health->down);
        $this->assertSame(0, $health->totalRequests);
        $this->assertSame(0, $health->totalErrors);
        $this->assertSame(0, $health->uptimeSeconds);
        $this->assertSame([], $health->instances);
    }

    public function testFromArrayWithPartialInstances(): void
    {
        $data = [
            'instances' => [
                ['hash' => 'only-hash'],
                ['alive' => true],
                ['requests' => 777],
            ],
        ];

        $health = Health::fromArray($data);

        $this->assertCount(3, $health->instances);

        $this->assertSame('only-hash', $health->instances[0]->hash);
        $this->assertFalse($health->instances[0]->alive);
        $this->assertSame('closed', $health->instances[0]->cbState);
        $this->assertSame(0, $health->instances[0]->requests);
        $this->assertSame(0, $health->instances[0]->errors);

        $this->assertSame('', $health->instances[1]->hash);
        $this->assertTrue($health->instances[1]->alive);

        $this->assertSame(777, $health->instances[2]->requests);
    }

    public function testJsonSerialize(): void
    {
        $instance = new ApiInstanceHealth('abc', true, 'closed', 100, 0);
        $health = new Health(2, 0, 1000, 5, 7200, [$instance]);

        $expected = [
            'up' => 2,
            'down' => 0,
            'total_requests' => 1000,
            'total_errors' => 5,
            'uptime_seconds' => 7200,
            'instances' => [$instance->jsonSerialize()],
        ];

        $this->assertSame($expected, $health->jsonSerialize());
    }
}
