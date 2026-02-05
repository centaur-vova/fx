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
use Swoole\Atomic;
use Swoole\Coroutine as Co;
use Swoole\Timer;
use Swoole\WebSocket\Server;

// Config
$config = new App\Config(__DIR__);

// Server
$server = new Server(
    $config->get('SERVER_HOST', '0.0.0.0'),
    $config->getInt('SERVER_PORT', 9501)
);

$server->set([
    'worker_num' => $config->getInt('SERVER_WORKER_NUM', 4),
]);

// Infrastructure

$inFlightCounter = new Atomic(0);
$logger = new StdoutLogger();
$connectionPool = new ConnectionPool();
$mainQueue = new Co\Channel($config->getInt('QUEUE_CAPACITY', 10000));
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
    $inFlightCounter,
    $logger
);

// API/Controllers
$taskController = new TaskController($taskService, $wsHub);

// Router
$router = new Router($taskController);

// EventHandler
$eventHandler = new EventHandler($router, $wsHub, $connectionPool, $inFlightCounter);

// WS
$server->on('request', $eventHandler->onRequest(...));
$server->on('open', $eventHandler->onOpen(...));
$server->on('message', $eventHandler->onMessage(...));
$server->on('close', $eventHandler->onClose(...));

// Workers
$server->on('WorkerStart', function ($server, $workerId) use ($config, $taskService, $eventHandler) {
    Co::create(function () use ($taskService, $server, $workerId) {
        $taskService->startWorker($server, $workerId);
    });
    Co::create(function () use ($taskService, $server, $workerId) {
        $taskService->startWorker($server, $workerId);
    });

    Timer::after(100, function () use ($eventHandler, $config) {
        $eventHandler->registerMetricsTimer($config->getInt('METRICS_UPDATE_INTERVAL_MS', 1000));
        echo ">>> [System] Metrics Engine Started on Worker #0\n";
    });
});

echo "========================================\n";
echo "Swoole HTTP/WebSocket server started\n";
echo "URL: http://0.0.0.0:9501\n";
echo "WebSocket: ws://localhost:9501\n";
echo "Static files: http://localhost:9501/dist/\n";
echo "========================================\n";

$server->start();
