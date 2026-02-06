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
        $data = [
            'action' => 'broadcast_ws',
            'payload' => $payload,
        ];
        $message = json_encode($data);
        $currentWorkerId = $this->server->worker_id;

        for ($i = 0; $i < $this->server->setting['worker_num']; $i++) {
            if ($i === $currentWorkerId) {
                $this->localBroadcast($payload);
                continue;
            }

            $this->server->sendMessage($message, $i);
        }
    }

    public function localBroadcast(array $payload): void
    {
        $json = json_encode($payload);
        foreach ($this->connectionPool as $fd => $row) {
            $fd = (int) $fd;

            if ($this->server->getWorkerId($fd) === $this->server->worker_id) {
                if ($this->server->exists($fd) && $this->server->isEstablished($fd)) {
                    $this->server->push($fd, $json);
                }
            }
        }
    }

    public function count(): int
    {
        return count($this->connectionPool);
    }
}
