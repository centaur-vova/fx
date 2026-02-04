<?php

namespace App\Server;

use App\Router;
use App\WebSocket\MessageHub;
use App\WebSocket\ConnectionPool;
use Swoole\WebSocket\Server;

class EventHandler
{
    public function __construct(
        private Router $router,
        private MessageHub $wsHub,
        private ConnectionPool $connectionPool
    ) {
    }

    public function onRequest($request, $response): void
    {
        $this->router->handle($request, $response);
    }

    public function onOpen(Server $server, $request): void
    {
        $this->connectionPool->add($request->fd);
    }

    public function onMessage(Server $server, $frame): void
    {
        $data = json_decode($frame->data, true);
        if (isset($data['event'])) {
            switch ($data['event']) {
                case 'pusher:ping':
                    $server->push($frame->fd, json_encode(['event' => 'pusher:pong']));
                    break;
                case 'pusher:subscribe':
                    $server->push($frame->fd, json_encode([
                        'event' => 'pusher_internal:subscription_succeeded',
                        'channel' => $data['data']['channel'],
                    ]));
                    break;
            }
        }
    }

    public function onClose(Server $server, $fd): void
    {
        $this->connectionPool->remove($fd);
    }
}
