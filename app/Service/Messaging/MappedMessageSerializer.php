<?php

declare(strict_types=1);

namespace App\Service\Messaging;

use App\Contract\Messaging\MessageSerializer;
use App\DTO\Task\TaskExecutionPayload;
use App\DTO\WebSocket\Message\TaskStatusUpdate;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final readonly class MappedMessageSerializer implements MessageSerializer
{
    private const array MAP = [
        // => frontend
        'task.status.update' => TaskStatusUpdate::class,

        // Internal task handling
        'task.execute' => TaskExecutionPayload::class,

        // add more types..
    ];

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function serialize(object $message): string
    {
        $type = array_search($message::class, self::MAP, true);

        if (!$type) {
            throw new RuntimeException('Class ' . $message::class . ' is not mapped.');
        }

        return json_encode([
            '_t' => $type,
            'd' => $message,
        ], JSON_THROW_ON_ERROR);
    }

    public function unserialize(string $payload): ?object
    {
        $this->logger->debug('Unserializing raw payload', [
            'payload' => $payload,
        ]);

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new RuntimeException('Invalid message format: not an array');
            }

            if (!isset($data['_t']) || !is_string($data['_t'])) {
                throw new RuntimeException('Missing or invalid message type (_t)');
            }

            $type = $data['_t'];
            $class = $this->resolveClassName($type);

            $messageData = $data['d'] ?? [];
            if (!is_array($messageData)) {
                $messageData = [];
            }

            return new $class(...$messageData);
        } catch (JsonException|RuntimeException $e) {
            $this->logger->debug('Failure unserializing raw payload', [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function resolveClassName(string $type): string
    {
        return self::MAP[$type] ?? throw new RuntimeException("Unknown message type alias: $type");
    }
}
