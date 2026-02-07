<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tasks;

use App\Contracts\Monitoring\TaskCounter;
use App\Contracts\Tasks\TaskDelayStrategy;
use App\Contracts\Tasks\TaskSemaphore;
use App\Contracts\Websockets\Broadcaster;
use App\Exceptions\Tasks\QueueFullException;
use App\Services\Tasks\TaskService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TaskServiceTest extends TestCase
{
    public function test_create_batch_throws_exception_if_queue_is_full(): void
    {
        $config = new \App\Config([
            'QUEUE_CAPACITY' => 5,
        ]);

        $service = new TaskService(
            $this->createStub(TaskSemaphore::class),
            $this->createStub(Broadcaster::class),
            $this->createStub(TaskDelayStrategy::class),
            $this->createStub(TaskCounter::class),
            $this->createStub(LoggerInterface::class),
            $config
        );

        $this->expectException(QueueFullException::class);
        $service->createBatch(10, 0, 2);
    }
}
