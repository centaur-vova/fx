<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Contract\Storage\CacheStorage;
use App\Contract\Support\Concern\LoopedLogger;
use App\Contract\Support\Identifiable;
use App\Contract\Task\TaskQueue;
use App\DTO\Task\TaskExecutionPayload;
use App\Exception\Server\WorkerShutdownException;
use App\Server\Options;
use App\Server\RuntimeContext;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Server;

final class TaskQueueManager
{
    use LoopedLogger;

    private const string RECEIPT_PREFIX = 'receipt:';

    public function __construct(
        private readonly RuntimeContext $context,
        private readonly TaskQueue $taskQueue,
        private readonly CacheStorage $taskMetaCache,
        private readonly Options $options,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Continuously pulls tasks from NATS and dispatches them to Swoole task workers
     */
    public function run(Server $server): void
    {
        go(function () use ($server): void {
            $maxActive = $this->options->taskMaxActive;
            $reserve = (int) ($maxActive / 100); // to not to exceed the max. amount

            while (true) {
                if ($this->context->isShuttingDown()) {
                    $this->logger->info('TaskQueueManager is shutting down');
                    break;
                }

                // Calc free slots count
                $activeTasks = $this->taskMetaCache->count();
                $freeSlots = $maxActive - $activeTasks - $reserve;

                if ($freeSlots <= 0) {
                    // The task meta cache table is full, wait
                    Co::sleep(0.1);
                    continue;
                }

                // Calculate batch size
                $batchSize = min($this->options->queuePrefetchBatch, $freeSlots);
                if ($batchSize <= 0) {
                    Co::sleep(0.1);
                    continue;
                }

                // Log only when we are actually about to pull data
                $this->logMemoryUsage();
                $this->logLoopDebug('Loop iteration', ['freeSlots' => $freeSlots]);

                $taskCount = 0;
                $tasks = $this->taskQueue->pull($batchSize);

                try {
                    $taskCount = $this->pumpToTasks($server, $tasks);

                } catch (WorkerShutdownException) {
                    // Shutting down, simply return
                    return;
                } catch (\Throwable $e) {
                    $this->logger->error('Manager loop failed. Retrying in 5s...', [
                        'error' => $e->getMessage(),
                    ]);

                    // Wait & continue
                    try {
                        $this->context->sleepOrDie(5.0);
                    } catch (WorkerShutdownException) {
                        return;
                    }
                }

                // Perfect adaptive delay
                Co::sleep($taskCount ? 0.01 : 0.1);
            }
        });
    }

    public function ack(Identifiable $task): void
    {
        $receiptKey = $this->getReceiptKey($task);
        $receiptId = $this->taskMetaCache->get($receiptKey);
        if ($receiptId) {
            $this->taskQueue->ack($receiptId);
            $this->taskMetaCache->delete($receiptKey);
        }
    }

    public function nack(Identifiable $task): void
    {
        $receiptKey = $this->getReceiptKey($task);
        $receiptId = $this->taskMetaCache->get($receiptKey);
        if ($receiptId) {
            $this->taskQueue->nack($receiptId);
            $this->taskMetaCache->delete($receiptKey);
        }
    }

    private function pumpToTasks(Server $server, \Generator $tasks): int
    {
        $taskCount = 0;

        /** @var string $receiptId */
        foreach ($tasks as $receiptId => $task) {
            $taskCount++;

            if (!$task instanceof TaskExecutionPayload) {
                $this->taskQueue->ack($receiptId);
                continue;
            }

            $this->logger->debug('Task received', ['id' => $task->id, 'receipt' => $receiptId]);
            $taskId = $server->task($task);
            $this->logger->debug('Task sent to worker', ['id' => $task->id]);

            if ($taskId === false) {
                $this->taskQueue->nack($receiptId);
                continue;
            }

            // Save to task meta
            $this->taskMetaCache->set(
                $this->getReceiptKey($task),
                $receiptId,
                $this->options->taskMetaTtlSec
            );
        }

        return $taskCount;
    }

    private function getReceiptKey(Identifiable $task): string
    {
        return self::RECEIPT_PREFIX . $task->getId();
    }
}
