<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\DTO\Task\TaskExecutionPayload;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests - for ponies
 */
class TaskStatusUpdateTest extends TestCase
{
    public function test_json_serialization_is_correct(): void
    {
        $payload = new TaskExecutionPayload(
            id: 1,
            mc: 10,
            mode: TaskMode::STRESS,
            sem: SemaphoreDriver::API,
        );
        $dto = TaskStatusUpdate::completed($payload, 2);
        $json = $dto->jsonSerialize();

        $this->assertEquals(1, $json['id']);
        $this->assertEquals('completed', $json['status']);
        $this->assertEquals(100, $json['progress']);
        $this->assertEquals(2, $json['worker']);
    }
}
