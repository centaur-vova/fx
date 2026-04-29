<?php

declare(strict_types=1);

namespace App\Contract\Queue;

interface Queue
{
    /**
     * Publish a message to the queue
     * @param array<string, string|string[]> $headers
     */
    public function publish(string $subject, object $data, array $headers = []): bool;

    /**
     * Create a consumer for this queue
     */
    public function getConsumer(string $name): Consumer;

    /**
     * Get number of messages in queue
     */
    public function count(): int;

    /**
     * Get queue information/stats
     *
     * @return array{
     *     name: string,
     *     messages: int,
     *     bytes: int,
     *     first_seq: int,
     *     last_seq: int
     * }
     */
    public function info(): array;

    /**
     * Acknowledge a message by its receipt ID
     *
     * @param string $receiptId The receipt identifier (e.g., NATS replyTo, Redis entry ID)
     */
    public function ack(string $receiptId): bool;

    /**
     * Negative acknowledge a message (reject/re-queue)
     *
     * @param string $receiptId The receipt identifier
     * @param int $delay Delay before message becomes available again (in seconds)
     */
    public function nack(string $receiptId, int $delay = 0): bool;
}
