<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Router;
use App\Services\Tasks\Semaphores\SwooleChannelSemaphore;
use App\Services\Tasks\Strategies\DemoDelayStrategy;
use App\Services\Tasks\TaskService;
use App\Support\StdoutLogger;
use App\WebSocket\{ConnectionPool, MessageHub, WsEventBroadcaster};
use Swoole\Coroutine as Co;
use Swoole\Timer;
use Swoole\WebSocket\Server;

$server = new Server("0.0.0.0", 9501);

$connectionPool = new ConnectionPool();

$wsHub = new MessageHub($server, $connectionPool);

$broadcaster = new WsEventBroadcaster($wsHub);

$semaphore = new SwooleChannelSemaphore();
$mainQueue = new Co\Channel(10000);
$logger = new StdoutLogger();
$strategy = new DemoDelayStrategy();

$taskService = new TaskService(
    $semaphore,
    $broadcaster,
    $mainQueue,
    $strategy,
    $logger
);

$taskController = new App\Controllers\TaskController($taskService, $wsHub);

$router = new Router($taskController);

$server->on('request', function ($request, $response) use ($router) {
    $router->handle($request, $response);
});

$server->on('open', function (Server $server, $request) use ($connectionPool) {
    $connectionPool->add($request->fd);
});

$server->on('close', function (Server $server, $fd) use ($connectionPool) {
    $connectionPool->remove($fd);
});

$server->on('message', function (Server $server, $frame) use ($wsHub) {
    $data = json_decode($frame->data, true);
    $fd = $frame->fd;

    if (isset($data['event'])) {
        switch ($data['event']) {
            case 'pusher:ping':
                $server->push($fd, json_encode(['event' => 'pusher:pong']));
                break;
            case 'pusher:subscribe':
                $server->push($fd, json_encode([
                    'event' => 'pusher_internal:subscription_succeeded',
                    'channel' => $data['data']['channel'],
                ]));
                break;
        }
    }
});

$server->on('WorkerStart', function ($server, $workerId) use ($taskService, $mainQueue) {
    for ($i = 0; $i < 10; $i++) {
        \Swoole\Coroutine::create(function () use ($mainQueue, $taskService) {
            while (true) {
                $task = $mainQueue->pop();

                if (!$task) {
                    continue;
                }

                \Swoole\Coroutine::create(function () use ($taskService, $task) {
                    $taskService->processTask($task['id'], $task['mc']);
                });
            }
        });
    }
});


Timer::tick(1000, function () use ($wsHub) {
    $load = sys_getloadavg();
    $cpu = $load ? round($load[0] * 10, 1) : 0;

    $wsHub->broadcast([
        'event' => 'metrics.update',
        'data' => [
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'connections' => $wsHub->count(),
            'cpu' => $cpu . '%'
        ]
    ]);
});

echo "========================================\n";
echo "Swoole HTTP/WebSocket server started\n";
echo "URL: http://0.0.0.0:9501\n";
echo "WebSocket: ws://localhost:9501\n";
echo "Static files: http://localhost:9501/dist/\n";
echo "========================================\n";

$server->start();
