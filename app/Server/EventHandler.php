<?php

declare(strict_types=1);

namespace App\Server;

use App\Config;
use App\Contracts\Monitoring\TaskCounter;
use App\Router;
use App\Services\Tasks\TaskService;
use App\WebSocket\ConnectionPool;
use Swoole\Timer;
use Swoole\WebSocket\Server;

class EventHandler
{
    public function __construct(
        private readonly Router $router,
        private readonly ConnectionPool $connectionPool,
        private readonly TaskCounter $taskCounter,
        private readonly TaskService $taskService,
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

        $lastUsage = getrusage();
        $lastTime = microtime(true);

        Timer::tick($interval, function ($timerId) use ($server, $fd, &$lastUsage, &$lastTime) {
            // In case of disconnect clear the timer
            if (!$server->exists($fd)) {
                Timer::clear($timerId);
                return;
            }

            $currentUsage = getrusage();
            $currentTime = microtime(true);

            // Calculate time delta (ms)
            $userDelta = ($currentUsage['ru_utime.tv_sec'] + $currentUsage['ru_utime.tv_usec'] / 1000000)
                - ($lastUsage['ru_utime.tv_sec'] + $lastUsage['ru_utime.tv_usec'] / 1000000);
            $sysDelta = ($currentUsage['ru_stime.tv_sec'] + $currentUsage['ru_stime.tv_usec'] / 1000000)
                - ($lastUsage['ru_stime.tv_sec'] + $lastUsage['ru_stime.tv_usec'] / 1000000);

            $timeDelta = $currentTime - $lastTime;

            // Load = (process_time / real_time) * 100 (%)
            $cpuUsage = round((($userDelta + $sysDelta) / $timeDelta) * 100, 2) . '%';

            // Update outer state
            $lastUsage = $currentUsage;
            $lastTime = $currentTime;

            $payload = [
                'event' => 'metrics.update',
                'data' => [
                    'worker' => $server->worker_id,
                    'memory' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
                    'connections' => $this->connectionPool->count(),
                    'cpu' => $cpuUsage,
                    'tasks' => $this->taskCounter->get(),
                    'queue' => $this->taskService->getQueueStats(),
                ],
            ];

            $server->push($fd, json_encode($payload));
        });
    }
}
