<?php

declare(strict_types=1);

namespace App\Service\Queue\Nats;

use App\Contract\Messaging\MessageSerializer;
use App\Contract\Queue\Consumer;
use App\Contract\Queue\Queue;
use Basis\Nats\Client as NatsClient;
use Basis\Nats\Message\Ack;
use Basis\Nats\Message\Nak;
use Basis\Nats\Message\Payload;
use Basis\Nats\Stream\Stream;

class NatsQueue implements Queue
{
    private readonly Stream $stream;

    public function __construct(
        private readonly NatsClient $client,
        private readonly MessageSerializer $serializer,
        private readonly string $streamName,
    ) {
        $this->stream = $client->getApi()->getStream($streamName);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function publish(string $subject, object $data, array $headers = []): bool
    {
        $payload = new Payload(
            body: $this->serializer->serialize($data),
            headers: $headers
        );

        try {
            $this->stream->put($subject, $payload);
            return true;
        } catch (\Throwable) {
            // TODO: log error
            return false;
        }
    }

    /**
     * Create a consumer
     */
    public function getConsumer(string $name): Consumer
    {
        $consumer = $this->stream->getConsumer($name);

        return new NatsConsumer($consumer);
    }

    public function count(): int
    {
        try {
            /** @var object{state: object{messages: int}} $info */
            $info = $this->stream->info();
            return $info->state->messages ?? 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array{
     *     name: string,
     *     messages?: int,
     *     bytes?: int,
     *     first_seq?: int,
     *     last_seq?: int,
     *     error?: string
     * }
     */
    public function info(): array
    {
        try {
            /** @var object{state: object{messages: int, bytes: int, first_seq: int, last_seq: int}} $info */
            $info = $this->stream->info();
            return [
                'name' => $this->streamName,
                'messages' => intval($info->state->messages ?? 0),
                'bytes' => intval($info->state->bytes ?? 0),
                'first_seq' => intval($info->state->first_seq ?? 0),
                'last_seq' => intval($info->state->last_seq ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $this->streamName,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function ack(string $receiptId): bool
    {
        // receiptId = replyTo
        try {
            $this->client->publish($receiptId, new Ack([
                'subject' => $receiptId,
            ]));
            return true;
        } catch (\Throwable) {
            // TODO: log
            return false;
        }
    }

    public function nack(string $receiptId, int $delay = 0): bool
    {
        try {
            $this->client->publish($receiptId, new Nak([
                'subject' => $receiptId,
                'delay' => $delay,
            ]));
            return true;
        } catch (\Throwable) {
            // TODO: log
            return false;
        }
    }

    public function purge(): void
    {
        $this->stream->purge();
    }
}
