<?php

declare(strict_types=1);

namespace App\Contract\WebSocket;

interface BinarySerializable
{
    /**
     * Pack the object into a binary string for WebSocket transmission.
     */
    public function toBinary(): string;
}
