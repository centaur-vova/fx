<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\WebSocket\Message;

use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
use App\DTO\WebSocket\Message\TaskBatchCreated;
use PHPUnit\Framework\TestCase;

class TaskBatchCreatedTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $dto = new TaskBatchCreated(
            count: 100,
            mc: 10,
            mode: TaskMode::STRESS,
            sem: SemaphoreDriver::API
        );

        $this->assertSame(100, $dto->count);
        $this->assertSame(10, $dto->mc);
        $this->assertSame(TaskMode::STRESS, $dto->mode);
        $this->assertSame(SemaphoreDriver::API, $dto->sem);
    }

    public function testJsonSerialize(): void
    {
        $dto = new TaskBatchCreated(
            count: 42,
            mc: 5,
            mode: TaskMode::OBSERVATION,
            sem: SemaphoreDriver::SHARED
        );

        $expected = [
            'count' => 42,
            'mc' => 5,
            'mode' => 'observation',
            'sem' => 'shared',
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testJsonSerializeWithStressMode(): void
    {
        $dto = new TaskBatchCreated(
            count: 1,
            mc: 255,
            mode: TaskMode::STRESS,
            sem: SemaphoreDriver::API
        );

        $json = $dto->jsonSerialize();

        $this->assertSame('stress', $json['mode']);
        $this->assertSame('api', $json['sem']);
    }
}
