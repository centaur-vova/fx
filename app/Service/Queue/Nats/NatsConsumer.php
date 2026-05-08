<?php

declare(strict_types=1);

namespace App\Service\Queue\Nats;

use App\Contract\Queue\Consumer;
use App\Contract\Queue\Message;
use App\Contract\Task\TaskQueueConsumer;
use Basis\Nats\Consumer\Consumer as NatsStreamConsumer;
use Basis\Nats\Message\Msg;
use Basis\Nats\Queue;

class NatsConsumer implements Consumer, TaskQueueConsumer
{
    private ?Queue $queue = null;

    public function __construct(
        private readonly NatsStreamConsumer $consumer,
    ) {
    }

    public function pull(int $batch = 1): array
    {
        if (!$this->queue) {
            $this->queue = $this->consumer
                ->setBatching($batch)
                ->getQueue();
        }

        /** @var Msg[] $messages */
        $messages = $this->queue->fetchAll($batch);

        return array_filter(array_map(function (Msg $msg): ?NatsMessage {
            /** @var array{headers?: array<string, string|int>} $payload */
            $payload = (array) $msg->payload;
            /** @var array<string, string|int> $headers */
            $headers = $payload['headers'] ?? [];

            $replyTo = $msg->replyTo;

            $statusCode = (int) ($headers['Status-Code'] ?? 0);
            if ($statusCode >= 400) {
                return null;
            }

            if (empty($replyTo)) {
                return null;
            }

            $sequence = (int) ($headers['Nats-Sequence'] ?? 0);
            $attempts = (int) ($headers['Nats-Attempts'] ?? 0);

            return new NatsMessage(
                msg: $msg,
                subject: $msg->subject,
                sequence: $sequence,
                timestamp: time(),
                attempts: $attempts
            );
        }, $messages));
    }

    public function ack(Message $message): void
    {
        if (!$message instanceof NatsMessage) {
            return;
        }

        $message->ack();
    }

    public function nack(Message $message, ?int $delay = null): void
    {
        if (!$message instanceof NatsMessage) {
            return;
        }

        $message->nack($delay);
    }

    public function reject(Message $message): void
    {
        if (!$message instanceof NatsMessage) {
            return;
        }

        $message->reject();
    }

    public function close(): void
    {
        $this->queue = null;
        // Additional cleanup if needed
    }
}
