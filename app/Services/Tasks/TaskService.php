<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Contracts\Monitoring\TaskCounter;
use App\Contracts\Tasks\TaskDelayStrategy;
use App\Contracts\Tasks\TaskSemaphore;
use App\Contracts\Websockets\Broadcaster;
use App\DTO\WebSockets\Messages\TaskStatusUpdate;
use App\Exceptions\Task\QueueFullException;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Timer;
use Swoole\WebSocket\Server;

class TaskService
{
    public function __construct(
        private readonly Server $server,
        private readonly TaskSemaphore $semaphore,
        private readonly Broadcaster $broadcaster,
        private readonly TaskDelayStrategy $delayStrategy,
        private readonly TaskCounter $taskCounter,
        private readonly ProcessorFactory $processorFactory,
        private readonly LoggerInterface $logger,
        private readonly int $queueCapacity,
        private readonly int $maxRetries,
        private readonly int $retryDelaySec,
        private readonly float $lockTimeoutSec,
    ) {
    }

    /**
     * @throws QueueFullException
     */
    public function createBatch(int $count, int $delay, int $maxConcurrent, string $mode): void
    {
        // Try reserving tasks in the atomic
        $this->tryReserve($count);

        for ($i = 0; $i < $count; $i++) {
            $taskId = $this->generateTaskId();

            $this->notify(TaskStatusUpdate::queued($taskId, $maxConcurrent));

            $timerDelay = ($this->delayStrategy)($i, $delay);

            Timer::after($timerDelay, function () use ($taskId, $maxConcurrent, $mode): void {
                // Instead of pushing to local Channel, we push to Global Task Pool
                $this->server->task([
                    'id' => $taskId,
                    'mc' => $maxConcurrent,
                    'mode' => $mode,
                ]);
            });
        }
    }

    public function processTask(int $workerId, string $taskId, int $mc, string $mode, int $attempt = 0): void
    {
        try {
            $this->logger->info('Task processing attempt', ['id' => $taskId, 'mc' => $mc, 'mode' => $mode, 'attempt' => $attempt]);

            $permit = $this->semaphore->forLimit($mc);
            $this->notify(TaskStatusUpdate::checkLock($taskId, $mc));

            // Use a real timeout from config instead of 0.01
            // If the lock is not acquired within this time, we yield and retry later
            if (!$permit->acquire($this->lockTimeoutSec)) {
                if ($attempt >= $this->maxRetries) {
                    $this->logger->error('Max retries reached', ['id' => $taskId]);
                    $this->notify(TaskStatusUpdate::retriesFailed($taskId, $mc, $workerId, $this->maxRetries));
                    $this->decrementTaskCount();
                    return;
                }

                $this->notify(TaskStatusUpdate::lockFailed($taskId, $mc));

                // Yield execution to let other coroutines work
                Co::sleep($this->retryDelaySec);

                // Recursive retry inside the coroutine
                $this->processTask($workerId, $taskId, $mc, $mode, ++$attempt);
                return;
            }

            // --- LOCK ACQUIRED ---
            try {
                $this->notify(TaskStatusUpdate::lockAcquired($taskId, $mc));

                $processor = $this->processorFactory->get($mode);

                $processor->execute(function (int $progress) use ($taskId, $mc): void {
                    $this->notify(TaskStatusUpdate::progress($taskId, $mc, $progress)
                        ->withMessage($progress . '%'));
                    Co::sleep(0.001);
                });

                $this->notify(TaskStatusUpdate::completed($taskId, $mc, $workerId));
            } finally {
                $permit->release();
                $this->decrementTaskCount();
                $this->logger->debug('Lock released', ['id' => $taskId]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Fatal task error', ['id' => $taskId, 'error' => $e->getMessage()]);
            $this->decrementTaskCount();
            // Optionally notify about system error
        }
    }

    public function getTaskNum(): int
    {
        return $this->taskCounter->get();
    }

    public function shutdown(): void
    {
        $this->semaphore->close();
    }

    private function generateTaskId(): string
    {
        return sprintf('%s-%d', bin2hex(random_bytes(4)), time());
    }

    private function notify(TaskStatusUpdate $taskStatusUpdate): void
    {
        $this->broadcaster->broadcast('status.changed', $taskStatusUpdate);
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
