<?php

declare(strict_types=1);

namespace App\Support;

use App\Contract\Support\EventBus;

final class InternalBus implements EventBus
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Реализация ListenerProviderInterface
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = $event::class;
        return $this->listeners[$eventClass] ?? [];
    }

    /**
     * Реализация EventDispatcherInterface
     */
    public function dispatch(object $event): object
    {
        // Перебираем слушателей, которых выдал провайдер (мы сами)
        foreach ($this->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        return $event;
    }
}
