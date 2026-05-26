<?php

declare(strict_types=1);

namespace Tests\Unit\Server\Http\Request;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\Exception\Http\InvalidTaskBatchException;
use App\Server\Http\Request\CreateTasks;
use PHPUnit\Framework\TestCase;

class CreateTasksTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $payload = [
            'count' => 10,
            'max_concurrent' => 5,
            'task_mode' => 'stress',
            'semaphore_driver' => 'api',
        ];

        $dto = CreateTasks::fromArray($payload);

        $this->assertSame(10, $dto->count);
        $this->assertSame(5, $dto->maxConcurrent);
        $this->assertSame(TaskMode::STRESS, $dto->taskMode);
        $this->assertSame(SemaphoreDriver::API, $dto->semaphoreDriver);
        $this->assertFalse($dto->inRandomMode());
    }

    public function testFromArrayWithMissingData(): void
    {
        $payload = [];

        $dto = CreateTasks::fromArray($payload);

        $this->assertSame(1, $dto->count);
        $this->assertSame(2, $dto->maxConcurrent);
        $this->assertNull($dto->taskMode);
        $this->assertNull($dto->semaphoreDriver);
    }

    public function testInRandomMode(): void
    {
        $dto = new CreateTasks(0, 5, null, null);
        $this->assertTrue($dto->inRandomMode());

        $dto2 = new CreateTasks(10, 5, null, null);
        $this->assertFalse($dto2->inRandomMode());
    }

    public function testValidateRandomModeSkipsChecks(): void
    {
        $dto = new CreateTasks(0, 999, null, null);
        $dto->validate(100, 10);
        $this->assertTrue(true); // не выбросило исключение
    }

    public function testValidateInvalidCount(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $dto = new CreateTasks(1000, 5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $dto->validate(100, 10);
    }

    public function testValidateInvalidMaxConcurrent(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $dto = new CreateTasks(10, 20, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $dto->validate(100, 10);
    }

    public function testValidateMissingSemaphoreDriver(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $dto = new CreateTasks(10, 5, TaskMode::OBSERVATION, null);
        $dto->validate(100, 10);
    }

    public function testValidateMissingTaskMode(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $dto = new CreateTasks(10, 5, null, SemaphoreDriver::SHARED);
        $dto->validate(100, 10);
    }

    public function testValidateSuccess(): void
    {
        $dto = new CreateTasks(50, 5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $dto->validate(100, 10);
        $this->assertTrue(true); // не выбросило исключение
    }
}
