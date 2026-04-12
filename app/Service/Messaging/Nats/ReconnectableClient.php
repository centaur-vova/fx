<?php

declare(strict_types=1);

namespace App\Service\Messaging\Nats;

use Basis\Nats\Client;
use Basis\Nats\Connection;

class ReconnectableClient extends Client
{
    public function reconnect(): void
    {
        $this->connection?->close();
        $this->connection = new Connection(
            client: $this,
            logger: $this->logger
        );
    }
}
