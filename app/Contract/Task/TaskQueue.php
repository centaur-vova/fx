<?php

declare(strict_types=1);

namespace App\Contract\Task;

/**
 * Message queue interface for task scheduling and processing.
 *
 * Provides push/pull/ack/nack semantics with NATS JetStream compatibility.
 */
interface TaskQueue
{
    /**
     * Pushes a task into the queue.
     *
     * @param object $task The task object to queue
     *
     * @return bool True on success, false otherwise
     */
    public function push(object $task): bool;

    /**
     * Pulls tasks from the queue.
     *
     * @param int $limit Maximum number of tasks to pull
     *
     * @return \Generator<string, object> Yields receipt ID and task object
     */
    public function pull(int $limit = 10): \Generator;

    /**
     * Acknowledges successful processing of a task.
     *
     * @param string $receiptId The receipt ID returned from pull()
     */
    public function ack(string $receiptId): void;

    /**
     * Rejects a task, optionally requeuing it after a delay.
     *
     * @param string     $receiptId The receipt ID returned from pull()
     * @param int|null   $delay     Delay in seconds before requeuing (null = no retry)
     */
    public function nack(string $receiptId, ?int $delay = null): void;

    /**
     * Returns the current number of pending tasks in the queue.
     *
     * @return int Pending task count
     */
    public function count(): int;

    /**
     * Removes all pending tasks from the queue.
     */
    public function purge(): void;
}
