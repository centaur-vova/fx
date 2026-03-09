<?php

declare(strict_types=1);

namespace App\Contract\WebSocket;

interface Broadcaster
{
    /**
     * @param array<string, mixed>|\JsonSerializable $data
     */
    public function broadcast(string $event, array|\JsonSerializable $data): void;
}
