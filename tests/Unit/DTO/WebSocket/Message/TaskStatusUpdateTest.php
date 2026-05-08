<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreDriver;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests - for ponies
 */
class TaskStatusUpdateTest extends TestCase
{
    public function test_json_serialization_is_correct(): void
    {
        $dto = TaskStatusUpdate::completed(1, 10, 2, SemaphoreDriver::API);
        $json = $dto->jsonSerialize();

        $this->assertEquals(1, $json['id']);
        $this->assertEquals('completed', $json['status']);
        $this->assertEquals(100, $json['progress']);
        $this->assertEquals(2, $json['worker']);
    }
}
