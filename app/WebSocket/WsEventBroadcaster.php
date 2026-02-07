<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\Contracts\Websockets\Broadcaster;

class WsEventBroadcaster implements Broadcaster
{
    private const string CHANNEL = 'tasks';

    public function __construct(private readonly MessageHub $hub)
    {
    }

    public function broadcast(string $event, mixed $data): void
    {
        $payload = ($data instanceof \JsonSerializable)
                ? $data->jsonSerialize()
                : $data;

        $this->hub->broadcast([
            'event' => $event,
            'data' => $payload,
            'channel' => self::CHANNEL,
        ]);
    }
}
