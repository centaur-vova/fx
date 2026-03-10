<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Contract\Monitoring\TaskCounter;
use App\Contract\Support\EventBus;
use App\Contract\Task\TaskDelayStrategy;
use App\Contract\Task\TaskSemaphore;
use App\DTO\Task\TaskExecutionPayload;
use App\DTO\WebSocket\Event\TaskStatusChangedEvent;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use App\Exception\Task\QueueFullException;
use App\Service\Task\Processor\ProcessorFactory;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Timer;
use Swoole\WebSocket\Server;

class TaskService
{
    public function __construct(
        private readonly Server $server,
        private readonly TaskSemaphore $semaphore,
        private readonly TaskDelayStrategy $delayStrategy,
        private readonly TaskCounter $taskCounter,
        private readonly ProcessorFactory $processorFactory,
        private readonly LoggerInterface $logger,
        private readonly EventBus $bus,
        private readonly int $queueCapacity,
        private readonly int $maxRetries,
        private readonly int $retryDelaySec,
        private readonly float $lockTimeoutSec,
    ) {
    }

    /**
     * @throws QueueFullException
     */
    public function createBatch(int $count, int $maxConcurrent, string $mode): void
    {
        // Try reserving tasks in the atomic
        $this->tryReserve($count);

        for ($i = 0; $i < $count; $i++) {
            $taskId = $this->generateTaskId();

            $this->notify(TaskStatusUpdate::queued($taskId, $maxConcurrent));

            $timerDelay = ($this->delayStrategy)($i);

            Timer::after($timerDelay, function () use ($taskId, $maxConcurrent, $mode): void {
                // Instead of pushing to local Channel, we push to Global Task Pool
                $this->server->task(new TaskExecutionPayload(
                    id: $taskId,
                    mc: $maxConcurrent,
                    mode: $mode
                ));
            });
        }
    }

    public function processTask(TaskExecutionPayload $payload, int $workerId): void
    {
        try {
            $permit = $this->semaphore->forLimit($payload->mc);
            $this->notify(TaskStatusUpdate::checkLock($payload->id, $payload->mc));

            /**
             * Attempt to acquire lock.
             */
            if (!$permit->acquire((float) $this->lockTimeoutSec)) {
                if ($payload->attempt >= $this->maxRetries) {
                    $this->logger->info('Max retries reached', ['id' => $payload->id]);
                    $this->notify(TaskStatusUpdate::retriesFailed($payload->id, $payload->mc, $workerId, $this->maxRetries));
                    $this->decrementTaskCount();
                    return;
                }

                $this->notify(TaskStatusUpdate::lockFailed($payload->id, $payload->mc));

                /**
                 * Re-queue
                 */
                Timer::after($this->retryDelaySec * 1000, function () use ($payload): void {
                    $workerNum = (int) ($this->server->setting['worker_num'] ?? 1);
                    // Размазываем нагрузку и риски по всем воркерам
                    $targetWorkerId = random_int(0, $workerNum - 1);
                    $this->server->sendMessage($payload->incrAttempt(), $targetWorkerId);
                });

                return;
            }

            /**
             * LOCK ACQUIRED - Logic execution block
             */
            try {
                $this->notify(TaskStatusUpdate::lockAcquired($payload->id, $payload->mc));

                $processor = $this->processorFactory->get($payload->mode);

                $progressCallback = function (int $progress) use ($payload): void {
                    $this->notify(
                        TaskStatusUpdate::progress($payload->id, $payload->mc, $progress)
                            ->withMessage($progress . '%')
                    );
                    Co::sleep(0.001);
                };

                $processor->execute($progressCallback);

                $this->notify(TaskStatusUpdate::completed($payload->id, $payload->mc, $workerId));
            } finally {
                $permit->release();
                $this->decrementTaskCount();
                $this->logger->debug('Lock released', ['id' => $payload->id]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Fatal task error', ['id' => $payload->id, 'error' => $e->getMessage()]);
            // Emergency cleanup if something exploded before finally
            $this->decrementTaskCount();
        }
    }

    public function shutdown(): void
    {
        $this->semaphore->close();
    }

    private function generateTaskId(): int
    {
        $time = time(); // 4 bytes
        $random = random_int(0, 0xFFFFFFFF); // 4 random bytes

        return ($time << 32) | $random;
    }

    private function notify(TaskStatusUpdate $update): void
    {
        $this->bus->dispatch(new TaskStatusChangedEvent($update));
    }

    /**
     * @throws QueueFullException
     */
    private function tryReserve(int $count): void
    {
        $newTotal = $this->taskCounter->add($count);
        if ($newTotal > $this->queueCapacity) {
            // Rollback changes in the atomic
            $this->taskCounter->sub($count);

            throw new QueueFullException($this->queueCapacity);
        }
    }

    private function decrementTaskCount(): void
    {
        $this->taskCounter->decrement();
    }
}
