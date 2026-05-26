<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Http\Response;

use App\DTO\Balancer\ApiInstanceHealth;
use App\DTO\Balancer\Health;
use App\Server\Http\Response\HealthResponse;
use PHPUnit\Framework\TestCase;

class HealthResponseTest extends TestCase
{
    public function testJsonSerializeWithoutBalancer(): void
    {
        $response = new HealthResponse(
            status: 'ok',
            phpVersion: '8.4.19',
            memoryMb: 42.5,
            tasksInProgress: 3,
            taskWorkers: 6,
            idleWorkers: 3,
            taskLastCreated: 1234567890,
            balancerHealth: null,
        );

        $expected = [
            'status' => 'ok',
            'system' => [
                'php_version' => '8.4.19',
                'memory_mb' => 42.5,
            ],
            'queue' => [
                'tasks_in_progress' => 3,
                'task_workers' => 6,
                'idle_workers' => 3,
            ],
            'tasks' => [
                'last_created' => 1234567890,
            ],
            'balancer' => [],
        ];

        $this->assertSame($expected, $response->jsonSerialize());
    }

    public function testJsonSerializeWithBalancer(): void
    {
        $instance = new ApiInstanceHealth('abc', true, 'closed', 100, 0);
        $balancerHealth = new Health(2, 0, 1000, 5, 3600, [$instance]);

        $response = new HealthResponse(
            status: 'ok',
            phpVersion: '8.4.19',
            memoryMb: 50.0,
            tasksInProgress: 5,
            taskWorkers: 10,
            idleWorkers: 5,
            taskLastCreated: 1234567890,
            balancerHealth: $balancerHealth,
        );

        $result = $response->jsonSerialize();

        $this->assertSame(2, $result['balancer']['up']);
        $this->assertCount(1, $result['balancer']['instances']);
    }
}
