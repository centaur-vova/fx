<?php

declare(strict_types=1);

namespace App\WebSocket;

use Swoole\WebSocket\Server;

class MessageHub
{
    public function __construct(
        private Server $server,
        private ConnectionPool $connectionPool,
    ) {
    }

    public function broadcast(array $payload): void
    {
        $json = json_encode($payload);
        foreach ($this->connectionPool as $fd => $conn) {
            if ($this->server->isEstablished($fd) && $this->server->getWorkerId($fd) === $this->server->worker_id) {
                $this->server->push($fd, $json);
            }
        }
    }

    public function count(): int
    {
        return count($this->connectionPool);
    }
}
