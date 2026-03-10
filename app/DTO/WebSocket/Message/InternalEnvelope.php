<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Message;

use App\Contract\Support\Arrayable;
use App\Contract\WebSocket\BinarySerializable;
use App\WebSocket\Opcode;

final readonly class InternalEnvelope
{
    private const int FORMAT_JSON = 1;
    private const int FORMAT_BINARY = 2;

    /**
     * @param string|array<mixed> $payload
     */
    public function __construct(
        public string $action,
        public string|array $payload,
        public int $format = self::FORMAT_JSON,
    ) {
    }

    /**
     * Creates an envelope from input data
     *
     * @param array<mixed>|Arrayable|BinarySerializable $payload
     */
    public static function wrap(string $action, array|Arrayable|BinarySerializable $payload): self
    {
        if ($payload instanceof BinarySerializable) {
            return new self($action, $payload->toBinary(), self::FORMAT_BINARY);
        }

        $data = ($payload instanceof Arrayable) ? $payload->toArray() : $payload;

        $standardMessage = [
            'event' => $action,
            'data' => $data,
        ];

        return new self($action, $standardMessage, self::FORMAT_JSON);
    }

    /**
     * Restores envelope from a serialized string
     */
    public static function fromSerialized(string $message): ?self
    {
        /** @var array{a?: mixed, f?: mixed, p?: mixed}|null $data */
        $data = json_decode($message, true);

        if (!is_array($data) || !isset($data['a'], $data['f'], $data['p'])) {
            return null;
        }

        // If format is binary, decode from base64; otherwise, keep as is
        $rawPayload = $data['p'];

        if ($data['f'] === self::FORMAT_BINARY) {
            $payload = is_string($rawPayload) ? base64_decode($rawPayload, true) : '';
        } else {
            $payload = $rawPayload;
        }

        // Validate types before passing to constructor to satisfy PHPStan Level 9
        $action = is_scalar($data['a']) ? (string) $data['a'] : '';
        $format = is_scalar($data['f']) ? (int) $data['f'] : self::FORMAT_JSON;

        /** @var string|array<mixed> $payload */
        return new self(
            action: $action,
            payload: $payload,
            format: $format
        );
    }

    /**
     * Prepares data for WebSocket push
     */
    public function getFinalPayload(): string
    {
        if ($this->isBinary()) {
            return is_string($this->payload) ? $this->payload : '';
        }

        return json_encode($this->payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns whether the payload format is binary
     */
    public function isBinary(): bool
    {
        return $this->format === self::FORMAT_BINARY;
    }

    /**
     * Returns the Swoole Opcode type
     */
    public function getOpcode(): int
    {
        return $this->isBinary()
            ? Opcode::BINARY
            : Opcode::TEXT;
    }

    /**
     * Serialization for sendMessage (inter-worker communication)
     * Uses JSON as a transport wrapper
     */
    public function serialize(): string
    {
        $payload = ($this->isBinary() && is_string($this->payload))
            ? base64_encode($this->payload)
            : $this->payload;

        return json_encode([
            'a' => $this->action,
            'f' => $this->format,
            'p' => $payload,
        ], JSON_THROW_ON_ERROR);
    }
}
