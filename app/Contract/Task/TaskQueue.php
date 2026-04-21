<?php

declare(strict_types=1);

namespace App\Contract\Task;

interface TaskQueue
{
    public function push(object $task): bool;

    /**
     * @return \Generator<string, object>
     */
    public function pull(int $limit = 10): \Generator;

    public function ack(string $receiptId): void;

    public function nack(string $receiptId, ?int $delay = null): void;

    public function count(): int;

    public function purge(): void;
}
