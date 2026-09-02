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
    }

    public function testFromArrayWithMissingData(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $this->expectExceptionMessage('Invalid semaphore driver or task mode');

        CreateTasks::fromArray([]);
    }

    public function testFromArrayWithInvalidSemaphoreDriver(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $this->expectExceptionMessage('Invalid semaphore driver or task mode');

        $payload = [
            'count' => 10,
            'max_concurrent' => 5,
            'task_mode' => 'stress',
            'semaphore_driver' => 'invalid',
        ];

        CreateTasks::fromArray($payload);
    }

    public function testValidateInvalidCount(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $this->expectExceptionMessage('Count must be between 1 and 100');

        $dto = new CreateTasks(1000, 5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $dto->validate(100, 10);
    }

    public function testValidateInvalidMaxConcurrent(): void
    {
        $this->expectException(InvalidTaskBatchException::class);
        $this->expectExceptionMessage('Concurrency must be between 1 and 10');

        $dto = new CreateTasks(10, 20, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $dto->validate(100, 10);
    }

    public function testValidateSuccess(): void
    {
        $dto = new CreateTasks(50, 5, TaskMode::OBSERVATION, SemaphoreDriver::SHARED);
        $dto->validate(100, 10);
        $this->assertTrue(true);
    }
}
