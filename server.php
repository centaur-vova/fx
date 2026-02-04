<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Controllers\TaskController;
use App\Router;
use App\Server\EventHandler;
use App\Services\Tasks\Semaphores\SwooleChannelSemaphore;
use App\Services\Tasks\Strategies\DemoDelayStrategy;
use App\Services\Tasks\TaskService;
use App\Support\StdoutLogger;
use App\WebSocket\{ConnectionPool, MessageHub, WsEventBroadcaster};
use Swoole\Coroutine as Co;
use Swoole\Timer;
use Swoole\WebSocket\Server;

$server = new Server("0.0.0.0", 9501);

// Infrastructure
$logger = new StdoutLogger();
$connectionPool = new ConnectionPool();
$mainQueue = new Co\Channel(10000);
$semaphore = new SwooleChannelSemaphore();
$strategy = new DemoDelayStrategy();

// WebSocket
$wsHub = new MessageHub($server, $connectionPool);
$broadcaster = new WsEventBroadcaster($wsHub);

// TaskService
$taskService = new TaskService(
    $semaphore,
    $broadcaster,
    $mainQueue,
    $strategy,
    $logger
);

// API/Controllers
$taskController = new TaskController($taskService, $wsHub);

// Router
$router = new Router($taskController);

// EventHandler
$eventHandler = new EventHandler($router, $wsHub, $connectionPool);

$server->on('request', $eventHandler->onRequest(...));
$server->on('open', $eventHandler->onOpen(...));
$server->on('message', $eventHandler->onMessage(...));
$server->on('close', $eventHandler->onClose(...));

$server->on('WorkerStart', $taskService->startWorker(...));

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
