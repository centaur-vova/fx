<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Contract\Storage\KeyValueStorage;
use App\Contract\Support\Identifiable;
use App\Contract\Task\TaskQueue;
use App\DTO\Task\TaskExecutionPayload;
use App\Server\Options;
use Swoole\Coroutine as Co;
use Swoole\Server;

final class TaskQueueManager
{
    private const string RECEIPT_PREFIX = 'receipt:';

    public function __construct(
        private readonly TaskQueue $taskQueue,
        private readonly KeyValueStorage $kvStorage,
        private readonly Options $options,
    ) {
    }

    /**
     * Continuously pulls tasks from NATS and dispatches them to Swoole task workers
     */
    public function run(Server $server): void
    {
        go(function () use ($server): void {
            $prefetchBatch = $this->options->queuePrefetchBatch;
            $taskQueueMultiplier = $this->options->taskQueueMultiplier;
            $taskWorkers = (int) ($server->setting['task_worker_num'] ?? 0);

            // Optional: limit max concurrent tasks (e.g., 20x workers)
            $maxConcurrentTasks = $taskWorkers * $taskQueueMultiplier;

            /** @phpstan-ignore-next-line */
            while (true) {
                $stats = $server->stats();
                $activeTasks = $stats['tasking_num'];

                // Check if we have capacity
                if ($activeTasks < $maxConcurrentTasks) {
                    $tasks = $this->taskQueue->pull($prefetchBatch);

                    foreach ($tasks as $receiptId => $task) {
                        if ($task instanceof TaskExecutionPayload) {
                            $taskId = $server->task($task);

                            if ($taskId === false) {
                                // Swoole queue full → return to NATS
                                $this->taskQueue->nack($receiptId);
                            } else {
                                // Successfully queued
                                // Save relation ID => receiptId
                                $this->kvStorage->set($this->getReceiptKey($task), $receiptId);
                            }
                        } else {
                            // Shouldn't really get here, let's ack it for now
                            $this->taskQueue->ack($receiptId);
                        }
                    }
                }

                Co::sleep(0.001); // Prevent CPU spinning
            }
        });
    }

    public function ack(Identifiable $task): void
    {
        $receiptId = $this->kvStorage->get($this->getReceiptKey($task));
        if ($receiptId) {
            $this->taskQueue->ack($receiptId);
            $this->kvStorage->delete($receiptId);
        }
    }

    public function nack(Identifiable $task): void
    {
        $receiptId = $this->kvStorage->get($this->getReceiptKey($task));
        if ($receiptId) {
            $this->taskQueue->nack($receiptId);
            $this->kvStorage->delete($receiptId);
        }
    }

    private function getReceiptKey(Identifiable $task): string
    {
        return self::RECEIPT_PREFIX . $task->getId();
    }
}
