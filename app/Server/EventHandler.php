<?php

declare(strict_types=1);

namespace App\Server;

use App\Config;
use App\Router;
use App\Services\Monitoring\SystemMonitor;
use App\WebSocket\ConnectionPool;
use Swoole\Timer;
use Swoole\WebSocket\Server;

class EventHandler
{
    public function __construct(
        private readonly Router $router,
        private readonly ConnectionPool $connectionPool,
        private readonly SystemMonitor $systemMonitor,
        private readonly Config $config,
    ) {
    }

    public function onRequest($request, $response): void
    {
        $this->router->handle($request, $response);
    }

    public function onOpen(Server $server, $request): void
    {
        $this->connectionPool->add((int) $request->fd);

        $this->startMetricsStream($server, $request->fd);
    }

    public function onMessage(Server $server, $frame): void
    {
        $data = json_decode((string) $frame->data, true);
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

    private function startMetricsStream(Server $server, int $fd): void
    {
        $interval = $this->config->getInt('METRICS_UPDATE_INTERVAL_MS', 1000);

        Timer::tick($interval, function ($timerId) use ($server, $fd) {
            // In case of disconnect clear the timer
            if (!$server->exists($fd)) {
                Timer::clear($timerId);
                return;
            }

            $payload = [
                'event' => 'metrics.update',
                'data' => $this->systemMonitor->capture($server),
            ];

            $server->push($fd, json_encode($payload));
        });
    }
}
