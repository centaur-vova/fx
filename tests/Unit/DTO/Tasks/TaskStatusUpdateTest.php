<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Tasks;

use App\DTO\Tasks\TaskStatusUpdate;
use PHPUnit\Framework\TestCase;

class TaskStatusUpdateTest extends TestCase
{
    public function test_it_creates_started_event_correctly(): void
    {
        $dto = TaskStatusUpdate::queued('task-123', 1);

        $this->assertEquals('task-123', $dto->id);
        $this->assertEquals(1, $dto->mc);
        $this->assertEquals('queued', $dto->status);
        $this->assertEquals(0, $dto->progress);
    }

    public function test_wither_pattern_is_immutable(): void
    {
        $dto = TaskStatusUpdate::queued('id', 1);
        $newDto = $dto->withMessage('New Message');

        $this->assertNotSame($dto, $newDto);
        $this->assertEquals('New Message', $newDto->message);
        $this->assertEquals('In queue', $dto->message);
    }

    public function test_json_serialization_contains_required_keys(): void
    {
        $dto = TaskStatusUpdate::completed('task-1', 1, 2);
        $serialized = $dto->jsonSerialize();

        $this->assertArrayHasKey('mc', $serialized);
        $this->assertArrayHasKey('taskId', $serialized);
        $this->assertArrayHasKey('status', $serialized);
        $this->assertArrayHasKey('message', $serialized);
        $this->assertArrayHasKey('progress', $serialized);
        $this->assertEquals('completed', $serialized['status']);
    }
}
