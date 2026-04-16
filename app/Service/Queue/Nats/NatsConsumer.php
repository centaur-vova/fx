<?php

declare(strict_types=1);

namespace App\Service\Queue\Nats;

use App\Contract\Queue\Consumer;
use App\Contract\Queue\Message;
use Basis\Nats\Consumer\Consumer as NatsStreamConsumer;
use Basis\Nats\Message\Msg;
use Basis\Nats\Queue;

class NatsConsumer implements Consumer
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

        $messages = $this->queue->fetchAll($batch);

        /** @phpstan-ignore-next-line */
        return array_filter(array_map(function (Msg $msg): ?NatsMessage {
            /** @var array<string, string|int> $headers */
            $headers = $msg->payload->headers ?? [];

            $replyTo = $msg->replyTo;

            $statusCode = (int) ($headers['Status-Code'] ?? 0);
            if ($statusCode >= 400) {
                // Error TODO:
                return null;
            }

            if (empty($replyTo)) {
                return null;
            }

            // Seq/attempts
            $sequence = (int) ($headers['Nats-Sequence'] ?? $msg->sequence ?? 0); // @phpstan-ignore-line
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
