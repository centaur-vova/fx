<?php

declare(strict_types=1);

namespace App\Service\Messaging\Nats;

use Basis\Nats\Client;
use Basis\Nats\Connection;
use Swoole\Coroutine as Co;
use Swoole\Timer;

class ReconnectableClient extends Client
{
    public function reconnect(): void
    {
        $this->connection?->close();
        $this->connection = null;

        Co::sleep(0.1);

        $this->connection = new Connection(
            client: $this,
            logger: $this->logger
        );

        $this->connection->ping();
    }

    public function startPingTimer(int $interval = 5): void
    {
        Timer::tick($interval * 1000, function (): void {
            try {
                $this->ping();
            } catch (\Throwable) {
                $this->reconnect();
            }
        });
    }
}
