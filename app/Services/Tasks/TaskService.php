<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Contracts\Tasks\TaskDelayStrategy;
use App\Contracts\Tasks\TaskSemaphore;
use App\Contracts\Websockets\Broadcaster;
use Psr\Log\LoggerInterface;
use Swoole\Atomic;
use Swoole\Coroutine as Co;
use Swoole\Coroutine\Channel;
use Swoole\Timer;

class TaskService
{
    public function __construct(
        private TaskSemaphore $semaphore,
        private Broadcaster $broadcaster,
        private Channel $mainQueue,
        private TaskDelayStrategy $delayStrategy,
        private Atomic $inFlightCounter,
        private LoggerInterface $logger,
    ) {
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
                $this->mainQueue->push([
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
        if ($permit->acquire(5)) {
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
            $this->mainQueue->push(['id' => $taskId, 'mc' => $mc]);
        }
    }

    public function startWorker($server, $workerId): void
    {
        for ($i = 0; $i < 10; $i++) {
            Co::create(function () {
                while (true) {
                    $task = $this->mainQueue->pop();

                    // Если канал закрыт, выходим из цикла
                    if ($this->mainQueue->errCode === SWOOLE_CHANNEL_CLOSED) {
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
        $this->inFlightCounter->add(1);
    }

    private function decrementTaskCount(): void
    {
        $this->inFlightCounter->sub(1);
    }
}
