<?php

declare(strict_types=1);

namespace App\Contract\Support;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Тот самый "Золотой интерфейс", который умеет всё.
 */
interface EventBus extends EventDispatcherInterface, ListenerProviderInterface
{
    /**
     * Кастомный метод для регистрации, которого нет в PSR,
     * но который нам нужен для жизни.
     */
    public function listen(string $eventClass, callable $listener): void;
}
