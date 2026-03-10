<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\DTO\WebSocket\Message\InternalEnvelope;
use Swoole\WebSocket\Server;

class MessageHub
{
    public function __construct(
        private readonly Server $server,
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function broadcast(InternalEnvelope $envelope): void
    {
        $serialized = $envelope->serialize();
        $currentWorkerId = $this->server->worker_id;

        for ($i = 0; $i < $this->server->setting['worker_num']; $i++) {
            if ($i === $currentWorkerId) {
                $this->localBroadcast($envelope);
                continue;
            }

            $this->server->sendMessage($serialized, $i);
        }
    }

    public function localBroadcast(InternalEnvelope $envelope): void
    {
        $currentWorker = (int) $this->server->worker_id;
        $data = $envelope->getFinalPayload();

        /**
         * @var int $fd
         * @var mixed $row
         */
        foreach ($this->connectionPool as $fd => $row) {
            // Level 9: Validate that $row is an array before accessing offsets
            if (!is_array($row)) {
                continue;
            }

            $workerId = $row[ConnectionPool::COL_WORKER_ID] ?? -1;

            // Level 9: Check if value is scalar before casting mixed to int
            if (is_scalar($workerId) && (int) $workerId === $currentWorker) {
                $fdInt = (int) $fd;

                if ($this->server->exists($fdInt) && $this->server->isEstablished($fdInt)) {
                    $this->server->push($fdInt, $data, $envelope->getOpcode());
                }
            }
        }
    }

    public function count(): int
    {
        return count($this->connectionPool);
    }
}
