<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Config;
use App\Contracts\Monitoring\TaskCounter;
use App\Contracts\Tasks\TaskDelayStrategy;
use App\Contracts\Tasks\TaskSemaphore;
use App\Contracts\Websockets\Broadcaster;
use App\DTO\Tasks\QueueStats;
use App\DTO\Tasks\TaskStatusUpdate;
use App\Exceptions\Tasks\QueueFullException;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Coroutine\Channel;
use Swoole\Timer;

class TaskService
{
    private ?Channel $mainQueue = null;

    public function __construct(
        private TaskSemaphore $semaphore,
        private Broadcaster $broadcaster,
        private TaskDelayStrategy $delayStrategy,
        private TaskCounter $taskCounter,
        private LoggerInterface $logger,
        private Config $config,
    ) {
        // Channel is now safe to create here as TaskService is worker-local
        $this->mainQueue = new Channel($this->config->getInt('QUEUE_CAPACITY', 10000));
    }

    /**
     * @throws QueueFullException
     */
    public function createBatch(int $count, int $delay, int $maxConcurrent): array
    {
        $capacity = (int) $this->config->get('QUEUE_CAPACITY', 10000);
        $currentQueueSize = $this->taskCounter->get();

        if (($currentQueueSize + $count) >= $capacity) {
            throw new QueueFullException($capacity);
        }

        $taskIds = [];
        for ($i = 0; $i < $count; $i++) {
            $taskId = $this->generateTaskId();
            $taskIds[] = $taskId;

            $this->notify(TaskStatusUpdate::queued($taskId, $maxConcurrent));

            $timerDelay = ($this->delayStrategy)($i, $delay);

            Timer::after($timerDelay, function () use ($taskId, $maxConcurrent) {
                $this->getQueue()->push([
                    'id' => $taskId,
                    'mc' => $maxConcurrent,
                ]);
            });
        }
        return $taskIds;
    }

    public function processTask(string $taskId, int $mc): void
    {
        $this->logger->info("Task started", ['id' => $taskId, 'mc' => $mc]);
        $this->notify(TaskStatusUpdate::processing($taskId, $mc));

        $permit = $this->semaphore->forLimit($mc);
        $this->notify(TaskStatusUpdate::checkLock($taskId, $mc));

        $startWait = microtime(true);

        $lockTimeout = (float) $this->config->get('TASK_LOCK_TIMEOUT_SEC', 30);

        if ($permit->acquire($lockTimeout)) {
            $waitDuration = microtime(true) - $startWait;
            $this->logger->debug("Lock acquired", ['id' => $taskId, 'wait' => $waitDuration]);

            try {
                $this->notify(TaskStatusUpdate::lockAcquired($taskId, $mc));

                for ($i = 1; $i <= 4; $i++) {
                    if ($this->getQueue()->errCode === SWOOLE_CHANNEL_CLOSED) {
                        return;
                    }

                    $stepDuration = mt_rand(800, 1300) / 1000;
                    Co::sleep($stepDuration);

                    $this->notify(TaskStatusUpdate::processingProgress($taskId, $mc, $i * 25)->withMessage("Step $i/4"));
                }

                $this->notify(TaskStatusUpdate::completed($taskId, $mc));
            } finally {
                $permit->release();
                $this->logger->debug("Lock released", ['id' => $taskId]);
            }
        } else {
            // In case of timeout or server is closed
            $waitDuration = microtime(true) - $startWait;

            // Dont push in case of closed server
            if ($this->getQueue()->errCode === SWOOLE_CHANNEL_CLOSED) {
                $this->logger->info("Task cancelled due to shutdown", ['id' => $taskId]);
                return;
            }

            $this->logger->warning("Lock timeout", ['id' => $taskId, 'waited' => $waitDuration]);
            $this->notify(TaskStatusUpdate::lockFailed($taskId, $mc));

            // Push only if channge is open
            $this->getQueue()->push(['id' => $taskId, 'mc' => $mc]);
        }
    }

    public function getQueueStats()
    {
        return new QueueStats(
            usage: $this->taskCounter->get(),
            max: (int) $this->config->get('QUEUE_CAPACITY', 10000)
        );
    }

    public function startWorker($server, $workerId): void
    {
        if ($this->mainQueue === null) {
            $this->mainQueue = new Co\Channel($this->config->getInt('QUEUE_CAPACITY', 10000));
        }

        $concurrentTasks = $this->config->getInt('WORKER_CONCURRENCY', 10);
        for ($i = 0; $i < $concurrentTasks; $i++) {
            Co::create(function () {
                try {
                    while (true) {
                        $task = @$this->getQueue()->pop();

                        if ($this->getQueue()->errCode === SWOOLE_CHANNEL_CLOSED) {
                            break;
                        }

                        if (!$task) {
                            continue;
                        }

                        Co::create(function () use ($task) {
                            $this->incrementTaskCount();
                            try {
                                $this->processTask($task['id'], $task['mc']);
                            } catch (\Throwable $e) {
                                // Ignore
                            } finally {
                                $this->decrementTaskCount();
                            }
                        });
                    }
                } catch (\Throwable $e) {
                    // Ignore
                }
            });
        }
    }

    public function shutdown(): void
    {
        if ($this->mainQueue) {
            $this->logger->info("Shutting down worker, waiting for queue to drain...");
            $this->mainQueue->close();
        }

        $this->semaphore->close();
    }

    private function generateTaskId(): string
    {
        return 'task-' . bin2hex(random_bytes(4)) . '-' . time();
    }

    private function notify(TaskStatusUpdate $taskStatusUpdate): void
    {
        $this->broadcaster->broadcast('task.status.changed', $taskStatusUpdate);
    }

    private function incrementTaskCount(): void
    {
        $this->taskCounter->increment();
    }

    private function decrementTaskCount(): void
    {
        $this->taskCounter->decrement();
    }

    private function getQueue(): Co\Channel
    {
        return $this->mainQueue ??= new Co\Channel(
            $this->config->getInt('QUEUE_CAPACITY', 10000)
        );
    }
}
