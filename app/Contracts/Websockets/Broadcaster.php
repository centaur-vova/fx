<?php

declare(strict_types=1);

namespace App\Contracts\Websockets;

interface Broadcaster
{
    public function broadcast(string $event, array|\JsonSerializable $data): void;
}
