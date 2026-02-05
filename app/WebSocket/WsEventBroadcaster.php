<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\Contracts\Websockets\Broadcaster;

class WsEventBroadcaster implements Broadcaster
{
    public function __construct(private MessageHub $hub)
    {
    }

    public function broadcast(string $event, mixed $data): void
    {
        $this->hub->broadcast([
            'event' => $event,
            'data' => $data,
            'channel' => 'tasks',
        ]);
    }
}
