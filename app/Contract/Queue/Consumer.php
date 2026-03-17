<?php

declare(strict_types=1);

namespace App\Contract\Queue;

interface Consumer
{
    /**
     * @return array<array-key, Message>
     */
    public function pull(int $batch = 1): array;

    public function ack(Message $message): void;

    public function nack(Message $message, ?int $delay = null): void;

    public function reject(Message $message): void;

    public function close(): void;
}
