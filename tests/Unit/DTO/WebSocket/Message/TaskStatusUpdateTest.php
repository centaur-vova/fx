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

    public function test_binary_serialization_matches_spec(): void
    {
        $id = 1000;
        $mc = 5;
        $progress = 50;
        $worker = 2;

        // Using constructor to set all fields including worker
        $dto = new TaskStatusUpdate(
            id: $id,
            status: TaskStatusUpdate::EVENT_PROGRESS,
            message: 'Progress',
            mc: $mc,
            progress: $progress,
            worker: $worker
        );

        $binary = $dto->toBinary();

        /**
         * Format CCJCCC (13 bytes total):
         * C (0x02) - 1 byte
         * C (status index 4) - 1 byte
         * J (Task ID 1000) - 8 bytes
         * C (MC 5) - 1 byte
         * C (Progress 50) - 1 byte
         * C (Worker 2) - 1 byte
         */
        $expected = pack('CCJCCC', 0x02, 4, $id, $mc, $progress, $worker);

        $this->assertSame($expected, $binary);
        $this->assertEquals(13, strlen($binary), 'Binary payload should be exactly 13 bytes');
    }

    public function test_binary_handles_null_worker_as_zero(): void
    {
        // Status EVENT_LOCK_FAILED is index 7 in eventMap
        $dto = TaskStatusUpdate::lockFailed(77, 3);
        $binary = $dto->toBinary();

        $expected = pack('CCJCCC', 0x02, 7, 77, 3, 0, 0);

        $this->assertSame($expected, $binary);
    }

    public function test_json_serialization_is_correct(): void
    {
        $dto = TaskStatusUpdate::completed(1, 10, 2);
        $json = $dto->jsonSerialize();

        $this->assertEquals(1, $json['taskId']);
        $this->assertEquals('completed', $json['status']);
        $this->assertEquals(100, $json['progress']);
        $this->assertEquals(2, $json['worker']);
    }
}
