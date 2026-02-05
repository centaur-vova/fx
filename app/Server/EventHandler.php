<?php

declare(strict_types=1);

namespace App\Server;

use App\Router;
use App\WebSocket\ConnectionPool;
use App\WebSocket\MessageHub;
use Swoole\Atomic;
use Swoole\Timer;
use Swoole\WebSocket\Server;

class EventHandler
{
    public function __construct(
        private Router $router,
        private MessageHub $wsHub,
        private ConnectionPool $connectionPool,
        private Atomic $inFlightCounter,
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

    public function registerMetricsTimer(int $updateIntervalMs): void
    {
        $hub = $this->wsHub;

        Timer::tick($updateIntervalMs, function () use ($hub) {
            $load = sys_getloadavg();
            $cpu = $load ? round($load[0] * 10, 1) : 0;

            $hub->broadcast([
                'event' => 'metrics.update',
                'data' => [
                    'tasks' => $this->inFlightCounter->get(),
                    'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                    'connections' => $this->wsHub->count(),
                    'cpu' => $cpu . '%',
                ],
            ]);
        });
    }
}
