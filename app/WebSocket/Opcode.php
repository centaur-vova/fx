<?php

declare(strict_types=1);

namespace App\WebSocket;

final readonly class Opcode
{
    public const int TEXT = 1;
    public const int BINARY = 2;
}
