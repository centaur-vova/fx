<?php

declare(strict_types=1);

namespace App\Service\Messaging\Nats;

use App\Contract\Messaging\Broadcaster;
use App\Contract\Messaging\MessageSerializer;
use Basis\Nats\Client as NatsClient;
use Basis\Nats\Message\Payload;
use Psr\Log\LoggerInterface;

final readonly class NatsBroadcaster implements Broadcaster
{
    public function __construct(
        private NatsClient $client,
        private MessageSerializer $serializer,
        private ?LoggerInterface $logger,
    ) {
    }

    public function publish(string $subject, mixed $message): void
    {
        $payload = match (true) {
            is_object($message) => $this->serializer->serialize($message),
            is_string($message) => $message,
            is_scalar($message) => (string) $message,
            default => throw new \InvalidArgumentException('Message must be object or string'),
        };

        try {
            $this->client->publish($subject, $payload);
            return;
        } catch (\Throwable) {
            $this->logger?->error('NATS publish failed, exiting worker...');
            // Restart worker
            exit(1);
        }
    }

    public function subscribe(string $subject, callable $handler, ?string $group = null): void
    {
        $wrapper = fn (\Basis\Nats\Message\Payload $payload) => $this->handleIncoming($payload, $handler);

        if ($group) {
            $this->client->subscribeQueue($subject, $group, $wrapper);
        } else {
            $this->client->subscribe($subject, $wrapper);
        }
    }

    public function process(float $timeout = 0.0): void
    {
        $this->client->process();
    }

    private function handleIncoming(Payload $payload, callable $callback): void
    {
        $callback($payload->body);
    }
}
