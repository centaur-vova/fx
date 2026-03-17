<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\WebSocket\Message;

use App\DTO\WebSocket\Message\TaskStatusUpdate;
use PHPUnit\Framework\TestCase;

class TaskStatusUpdateTest extends TestCase
{
    public function test_it_creates_queued_event_correctly(): void
    {
        $dto = TaskStatusUpdate::queued(123, 10);

        $this->assertEquals(123, $dto->id);
        $this->assertEquals(10, $dto->mc);
        $this->assertEquals(TaskStatusUpdate::EVENT_QUEUED, $dto->status);
    }

    public function test_wither_pattern_is_immutable(): void
    {
        $dto = TaskStatusUpdate::queued(1, 1);
        $newDto = $dto->withMessage('New Message');

        $this->assertNotSame($dto, $newDto);
        $this->assertEquals('New Message', $newDto->message);
    }

    public function test_json_serialization_is_correct(): void
    {
        $dto = TaskStatusUpdate::completed(1, 10, 2);
        $json = $dto->jsonSerialize();

        $this->assertEquals(1, $json['id']);
        $this->assertEquals('completed', $json['status']);
        $this->assertEquals(100, $json['progress']);
        $this->assertEquals(2, $json['worker']);
    }
}
