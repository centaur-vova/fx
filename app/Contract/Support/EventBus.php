<?php

declare(strict_types=1);

namespace App\Contract\Support;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

interface EventBus extends EventDispatcherInterface, ListenerProviderInterface
{
    public function listen(string $eventClass, callable $listener): void;
}
