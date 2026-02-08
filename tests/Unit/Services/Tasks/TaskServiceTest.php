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
use Swoole\WebSocket\Server;

class TaskServiceTest extends TestCase
{
    public function test_create_batch_throws_exception_if_queue_is_full(): void
    {
        $taskCounter = new class () implements TaskCounter {
            public int $count = 0;

            public function add(int $n = 1): int
            {
                return $this->count += $n;
            }

            public function sub(int $n = 1): int
            {
                return $this->count -= $n;
            }

            public function get(): int
            {
                return $this->count;
            }

            public function increment(): void
            {
                $this->count++;
            }

            public function decrement(): void
            {
                $this->count--;
            }
        };

        $service = new TaskService(
            taskCounter: $taskCounter,
            server: $this->createStub(Server::class),
            semaphore: $this->createStub(TaskSemaphore::class),
            broadcaster: $this->createStub(Broadcaster::class),
            delayStrategy: $this->createStub(TaskDelayStrategy::class),
            logger: $this->createStub(LoggerInterface::class),
            queueCapacity: 5,
            maxRetries: 3,
            retryDelaySec: 10,
            lockTimeoutSec: 3,
        );

        $this->expectException(QueueFullException::class);
        $service->createBatch(10, 0, 2);
    }
}
