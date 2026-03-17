<?php

declare(strict_types=1);

namespace App\Contract\Messaging;

interface Broadcaster
{
    public function publish(string $channel, mixed $message): void;

    public function subscribe(string $channel, callable $handler, ?string $group = null): void;

    public function process(float $timeout = 0.0): void;
}
