<?php

declare(strict_types=1);

namespace App\DTO\WebSockets;

use App\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class WsMessage implements Arrayable, JsonSerializable
{
    /**
     * @param string $event
     * @param array<string, mixed>|Arrayable $data
     */
    public function __construct(
        public string $event,
        public array|Arrayable $data = [],
    ) {
    }

    /**
     * Create a DTO from a payload array.
     *
     * We return null instead of throwing Exceptions to avoid overhead
     * in high-concurrency environments (garbage collection and stack trace allocation).
     */
    public static function fromArray(mixed $payload): ?self
    {
        // Fail fast: if payload is not an array or event is missing/invalid
        if (!is_array($payload) || !is_string($payload['event'] ?? null)) {
            return null;
        }

        $data = (isset($payload['data']) && is_array($payload['data']))
            ? $payload['data']
            : [];

        return new self(
            event: $payload['event'],
            data: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'data' => match(true) {
                $this->data instanceof Arrayable => $this->data->toArray(),
                default => $this->data,
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
