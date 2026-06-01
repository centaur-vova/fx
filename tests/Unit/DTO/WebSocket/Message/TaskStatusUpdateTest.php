<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\DTO\Task\TaskExecutionPayload;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use PHPUnit\Framework\TestCase;

class TaskStatusUpdateTest extends TestCase
{
    private TaskExecutionPayload $payload;

    protected function setUp(): void
    {
        $this->payload = new TaskExecutionPayload(
            id: 42,
            mc: 5,
            mode: TaskMode::OBSERVATION,
            sem: SemaphoreDriver::SHARED,
            traceparent: '00-abc-123-01',
            attempt: 1,
        );
    }

    public function testFromPayloadCreatesCorrectObject(): void
    {
        $dto = TaskStatusUpdate::fromPayload(
            $this->payload,
            'custom_status',
            'Custom message',
            3,
            50
        );

        $this->assertSame(42, $dto->id);
        $this->assertSame('custom_status', $dto->status);
        $this->assertSame(5, $dto->mc);
        $this->assertSame(SemaphoreDriver::SHARED, $dto->sem);
        $this->assertSame(TaskMode::OBSERVATION, $dto->mode);
        $this->assertSame('Custom message', $dto->message);
        $this->assertSame(3, $dto->worker);
        $this->assertSame(50, $dto->progress);
        $this->assertSame('00-abc-123-01', $dto->traceparent);
    }

    public function testRetry(): void
    {
        $dto = TaskStatusUpdate::retry($this->payload);

        $this->assertSame(TaskStatusUpdate::EVENT_RETRY, $dto->status);
        $this->assertSame('Retrying', $dto->message);
        $this->assertNull($dto->worker);
        $this->assertNull($dto->progress);
    }

    public function testCheckLock(): void
    {
        $dto = TaskStatusUpdate::checkLock($this->payload);

        $this->assertSame(TaskStatusUpdate::EVENT_CHECK_LOCK, $dto->status);
        $this->assertSame('Limit: 5', $dto->message);
    }

    public function testProgress(): void
    {
        $dto = TaskStatusUpdate::progress($this->payload, 75);

        $this->assertSame(TaskStatusUpdate::EVENT_PROGRESS, $dto->status);
        $this->assertSame('75%', $dto->message);
        $this->assertSame(75, $dto->progress);
    }

    public function testCompleted(): void
    {
        $dto = TaskStatusUpdate::completed($this->payload, 7);

        $this->assertSame(TaskStatusUpdate::EVENT_COMPLETED, $dto->status);
        $this->assertSame('Done', $dto->message);
        $this->assertSame(7, $dto->worker);
        $this->assertSame(100, $dto->progress);
    }

    public function testLockAcquired(): void
    {
        $dto = TaskStatusUpdate::lockAcquired($this->payload);

        $this->assertSame(TaskStatusUpdate::EVENT_LOCK_ACQUIRED, $dto->status);
        $this->assertSame('Accepted', $dto->message);
    }

    public function testLockFailed(): void
    {
        $dto = TaskStatusUpdate::lockFailed($this->payload);

        $this->assertSame(TaskStatusUpdate::EVENT_LOCK_FAILED, $dto->status);
        $this->assertSame('Timeout', $dto->message);
    }

    public function testRetriesFailed(): void
    {
        $dto = TaskStatusUpdate::retriesFailed($this->payload, 3, 5);

        $this->assertSame(TaskStatusUpdate::EVENT_RETRIES_FAILED, $dto->status);
        $this->assertSame('Max retries reached (5)', $dto->message);
        $this->assertSame(3, $dto->worker);
    }

    public function testJsonSerialize(): void
    {
        $dto = TaskStatusUpdate::completed($this->payload, 2);

        $expected = [
            'id' => 42,
            'status' => 'completed',
            'message' => 'Done',
            'mc' => 5,
            'progress' => 100,
            'worker' => 2,
            'sem' => 'shared',
            'mode' => 'observation',
            'traceparent' => '00-abc-123-01',
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testJsonSerializeWithoutOptionalFields(): void
    {
        $dto = new TaskStatusUpdate(
            id: 1,
            status: 'check_lock',
            mc: 10,
            sem: SemaphoreDriver::API,
            mode: TaskMode::STRESS,
            message: 'Checking lock',
        );

        $json = $dto->jsonSerialize();

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('mc', $json);
        $this->assertArrayHasKey('progress', $json);
        $this->assertNull($json['progress']);
        $this->assertArrayHasKey('worker', $json);
        $this->assertNull($json['worker']);
        $this->assertArrayHasKey('traceparent', $json);
        $this->assertNull($json['traceparent']);
    }
}
