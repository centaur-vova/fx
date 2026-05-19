<?php

declare(strict_types=1);

namespace App\Service\Queue\Nats;

use App\Contract\Messaging\MessageSerializer;
use App\Contract\Queue\Consumer;
use App\Contract\Queue\Message;
use App\Contract\Queue\Purgeable;
use App\Contract\Queue\Queue;
use App\Contract\Task\TaskQueue;

class NatsTaskQueue implements TaskQueue
{
    public function __construct(
        private readonly Queue $queue,
        private readonly MessageSerializer $serializer,
        private readonly Consumer $consumer,
        private readonly string $subject,
    ) {
    }

    public function push(object $task): bool
    {
        return $this->queue->publish($this->subject, $task);
    }

    public function pull(int $limit = 10): \Generator
    {
        $messages = $this->consumer->pull($limit);

        foreach ($messages as $msg) {
            $data = $msg->getData();
            if (!is_string($data)) {
                continue;
            }

            /** @var Message $msg */
            $task = $this->serializer->unserialize($data);
            if (is_object($task)) {
                yield $msg->getReceiptId() => $task;
                $msg->ack();
            }
        }
    }

    public function ack(string $receiptId): void
    {
        $this->queue->ack($receiptId);
    }

    public function nack(string $receiptId, ?int $delay = null): void
    {
        $this->queue->nack($receiptId, $delay ?? 0);
    }

    public function count(): int
    {
        return $this->queue->count();
    }

    public function purge(): void
    {
        if (!($this->queue instanceof Purgeable)) {
            throw new \RuntimeException('Purge not supported for this queue implementation');
        }
        $this->queue->purge();
    }
}
