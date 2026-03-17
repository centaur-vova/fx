<?php

declare(strict_types=1);

namespace App\Contract\Messaging;

interface MessageSerializer
{
    public function serialize(object $message): string;

    public function unserialize(string $payload): ?object;
}
