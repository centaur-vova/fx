<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Config;
use App\Contracts\Monitoring\TaskCounter;
use App\Contracts\Tasks\TaskDelayStrategy;
use App\Contracts\Tasks\TaskSemaphore;
use App\Contracts\Websockets\Broadcaster;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Coroutine\Channel;
use Swoole\Timer;
use Swoole\WebSocket\Server;

class TaskService
{
    private ?Channel $mainQueue = null;

    public function __construct(
        private Server $server,
        private TaskSemaphore $semaphore,
        private Broadcaster $broadcaster,
        private TaskDelayStrategy $delayStrategy,
        private TaskCounter $counter,
        private LoggerInterface $logger,
        private Config $config,
    ) {
        // Channel is now safe to create here as TaskService is worker-local
        $this->mainQueue = new Channel($this->config->getInt('QUEUE_CAPACITY', 10000));
    }

    public function createBatch(int $count, int $delay, int $maxConcurrent): array
    {
        $taskIds = [];
        for ($i = 0; $i < $count; $i++) {
            $taskId = $this->generateTaskId();
            $taskIds[] = $taskId;

            $this->notify($taskId, 'queued', 'In queue');

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
        $this->notify($taskId, 'processing', 'Started');

        $permit = $this->semaphore->forLimit($mc);
        $this->notify($taskId, 'check_lock', "Limit: $mc");

        $startWait = microtime(true);
        if ($permit->acquire(30)) {
            $waitDuration = microtime(true) - $startWait;
            $this->logger->debug("Lock acquired", ['id' => $taskId, 'wait' => $waitDuration]);

            try {
                $this->notify($taskId, 'lock_acquired', 'Accepted');

                for ($i = 1; $i <= 4; $i++) {
                    $stepDuration = mt_rand(800, 1300) / 1000;
                    Co::sleep($stepDuration);

                    $this->notify($taskId, 'processing_progress', "Step $i/4", $i * 25);
                }

                $this->notify($taskId, 'completed', 'Done', 100);
            } finally {
                $permit->release();
                $this->logger->debug("Lock released", ['id' => $taskId]);
            }
        } else {
            $waitDuration = microtime(true) - $startWait;
            $this->logger->warning("Lock timeout", ['id' => $taskId, 'waited' => $waitDuration]);

            $this->notify($taskId, 'lock_failed', 'Timeout');
            $this->getQueue()->push(['id' => $taskId, 'mc' => $mc]);
        }
    }

    public function startWorker($server, $workerId): void
    {
        if ($this->mainQueue === null) {
            $this->mainQueue = new Co\Channel($this->config->getInt('QUEUE_CAPACITY', 10000));
        }

        $concurrentTasks = $this->config->getInt('WORKER_CONCURRENCY', 10);
        for ($i = 0; $i < $concurrentTasks; $i++) {
            Co::create(function () {
                while (true) {
                    $task = $this->getQueue()->pop();

                    if ($this->getQueue()->errCode === SWOOLE_CHANNEL_CLOSED) {
                        break;
                    }

                    if ($task) {
                        Co::create(function () use ($task) {
                            $this->incrementTaskCount();
                            $this->processTask($task['id'], $task['mc']);
                            $this->decrementTaskCount();
                        });
                    }
                }
            });
        }
    }

    private function generateTaskId(): string
    {
        return 'task-' . bin2hex(random_bytes(4)) . '-' . time();
    }

    private function notify(string $id, string $status, string $msg, int $progress = 0): void
    {
        $this->broadcaster->broadcast('task.status.changed', [
            'taskId' => $id,
            'status' => $status,
            'message' => $msg,
            'progress' => $progress,
            'attempt' => 1,
        ]);
    }

    private function incrementTaskCount(): void
    {
        $this->counter->increment();
    }

    private function decrementTaskCount(): void
    {
        $this->counter->decrement();
    }

    private function getQueue(): Co\Channel
    {
        return $this->mainQueue ??= new Co\Channel(
            $this->config->getInt('QUEUE_CAPACITY', 10000)
        );
    }
}
