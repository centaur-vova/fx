<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Contract\Storage\KeyValueStorage;
use App\Contract\Support\Concern\LoopedLogger;
use App\Contract\Support\Identifiable;
use App\Contract\Task\TaskQueue;
use App\DTO\Task\TaskExecutionPayload;
use App\Server\Options;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine as Co;
use Swoole\Server;

final class TaskQueueManager
{
    use LoopedLogger;

    private const string RECEIPT_PREFIX = 'receipt:';

    public function __construct(
        private readonly TaskQueue $taskQueue,
        private readonly KeyValueStorage $taskMetaCache,
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
            $maxTableSize = $this->options->taskMetaCacheSize;
            $reserve = (int) ($maxTableSize / 100); // to not to exceed the max. amount

            /** @phpstan-ignore-next-line */
            while (true) {
                $this->logMemoryUsage();

                // Occupied slots count
                $activeTasks = $this->taskMetaCache->count();

                // Free slots count
                $freeSlots = $maxTableSize - $activeTasks - $reserve;
                $this->logLoopDebug('Loop iteration', ['freeSlots' => $freeSlots]);

                if ($freeSlots <= 0) {
                    // The task meta cache table is full, wait
                    Co::sleep(0.01);
                    continue;
                }

                // Do pull
                $batchSize = min($this->options->queuePrefetchBatch, $freeSlots);

                if ($batchSize <= 0) {
                    Co::sleep(0.001);
                    continue;
                }

                $tasks = $this->taskQueue->pull($batchSize);
                $tasksCount = 0;
                foreach ($tasks as $receiptId => $task) {
                    $tasksCount++;

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

                // Adaptive delay
                Co::sleep($tasksCount ? 0.001 : 0.1);
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

    private function getReceiptKey(Identifiable $task): string
    {
        return self::RECEIPT_PREFIX . $task->getId();
    }
}
