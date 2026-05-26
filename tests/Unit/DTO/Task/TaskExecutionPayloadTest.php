<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Task;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\DTO\Task\TaskExecutionPayload;
use PHPUnit\Framework\TestCase;

class TaskExecutionPayloadTest extends TestCase
{
    public function testCreateGeneratesValidPayload(): void
    {
        $payload = TaskExecutionPayload::create(5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);

        $this->assertSame(5, $payload->mc);
        $this->assertSame(TaskMode::OBSERVATION, $payload->mode);
        $this->assertSame(SemaphoreDriver::SHARED, $payload->sem);
        $this->assertGreaterThanOrEqual(0, $payload->id);
        $this->assertLessThanOrEqual(0x7FFFFFFF, $payload->id);
        $this->assertNull($payload->traceparent);
        $this->assertSame(0, $payload->attempt);
    }

    public function testCreateWithTraceparent(): void
    {
        $traceparent = '00-abc-123-01';
        $payload = TaskExecutionPayload::create(5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED, $traceparent);

        $this->assertSame($traceparent, $payload->traceparent);
    }

    public function testIncrAttemptReturnsNewInstance(): void
    {
        $payload = TaskExecutionPayload::create(5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $new = $payload->incrAttempt();

        $this->assertNotSame($payload, $new);
        $this->assertSame(1, $new->attempt);
        $this->assertSame($payload->id, $new->id);
        $this->assertSame($payload->mc, $new->mc);
        $this->assertSame($payload->mode, $new->mode);
        $this->assertSame($payload->sem, $new->sem);
    }

    public function testFromArrayHydratesCorrectly(): void
    {
        $data = [
            'id' => 12345,
            'mc' => 10,
            'mode' => 'stress',
            'sem' => 'api',
            'traceparent' => '00-abc-123-01',
            'attempt' => 3,
        ];

        $payload = TaskExecutionPayload::fromArray($data);

        $this->assertSame(12345, $payload->id);
        $this->assertSame(10, $payload->mc);
        $this->assertSame(TaskMode::STRESS, $payload->mode);
        $this->assertSame(SemaphoreDriver::API, $payload->sem);
        $this->assertSame('00-abc-123-01', $payload->traceparent);
        $this->assertSame(3, $payload->attempt);
    }

    public function testFromArrayWithMissingOptionalFields(): void
    {
        $data = [
            'id' => 12345,
            'mc' => 10,
            'mode' => 'observation',
            'sem' => 'shared',
        ];

        $payload = TaskExecutionPayload::fromArray($data);

        $this->assertSame(12345, $payload->id);
        $this->assertSame(10, $payload->mc);
        $this->assertSame(TaskMode::OBSERVATION, $payload->mode);
        $this->assertSame(SemaphoreDriver::SHARED, $payload->sem);
        $this->assertNull($payload->traceparent);
        $this->assertSame(0, $payload->attempt);
    }

    public function testGetIdReturnsStringId(): void
    {
        $payload = TaskExecutionPayload::create(5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $this->assertSame((string) $payload->id, $payload->getId());
    }
}
