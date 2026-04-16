<?php

declare(strict_types=1);

namespace App\Service\Queue\Nats;

use App\Contract\Queue\Message;
use Basis\Nats\Message\Msg;

class NatsMessage implements Message
{
    private bool $acknowledged = false;
    private bool $rejected = false;

    public function __construct(
        private readonly Msg $msg,
        private readonly string $subject,
        private readonly int $sequence,
        private readonly int $timestamp,
        private int $attempts = 0,
    ) {
    }

    public function getId(): string
    {
        return (string) $this->sequence;
    }

    public function getReceiptId(): string
    {
        return (string) $this->msg->replyTo;
    }

    public function getData(): mixed
    {
        return $this->msg->payload->body;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        /** @var array<string, string> $headers */
        $headers = $this->msg->payload->headers ?? [];
        return $headers;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function ack(): void
    {
        if ($this->acknowledged || $this->rejected) {
            return;
        }

        $this->msg->ack();
        $this->acknowledged = true;
    }

    public function nack(?int $delay = null): void
    {
        if ($this->acknowledged || $this->rejected) {
            return;
        }

        $this->msg->nack(($delay ?? 1) / 1000);
        $this->attempts++;
    }

    public function reject(): void
    {
        if ($this->acknowledged || $this->rejected) {
            return;
        }

        // nack?? DLQ?
        $this->msg->nack();
        $this->rejected = true;
    }
}
