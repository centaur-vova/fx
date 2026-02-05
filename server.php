<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// --- Imports (Keep your existing use statements) ---

use App\Config;
use App\Container;
use App\Contracts\Monitoring\TaskCounter;
use App\Controllers\TaskController;
use App\Router;
use App\Server\EventHandler;
use App\Server\SharedResourceProvider;
use App\Services\Tasks\Semaphores\SwooleChannelSemaphore;
use App\Services\Tasks\Strategies\DemoDelayStrategy;
use App\Services\Tasks\TaskService;
use App\Support\Monitoring\SwooleAtomicCounter;
use App\Support\StdoutLogger;
use App\WebSocket\ConnectionPool;
use App\WebSocket\MessageHub;
use App\WebSocket\WsEventBroadcaster;
use Swoole\Coroutine as Co;
use Swoole\Process;
use Swoole\WebSocket\Server;

// Initialize Infrastructure & Shared Memory
$config = new Config(__DIR__);
$shared = SharedResourceProvider::boot($config);

// Server Instance
$server = new Server(
    $config->get('SERVER_HOST', '0.0.0.0'),
    $config->getInt('SERVER_PORT', 9501)
);

$server->set([
    'worker_num' => $config->getInt('SERVER_WORKER_NUM', 4),
    'dispatch_mode' => $config->getInt('SERVER_DISPATCH_MODE', 2),
    'enable_coroutine' => true,
]);

// DI Container Configuration (Recipes only)
$container = new Container();

// Global shared instances
$container->set(Server::class, fn () => $server);
$container->set(Config::class, fn () => $config);
$container->set('shared.table.connections', fn () => $shared['connections']);
$container->set('shared.atomic.tasks', fn () => $shared['task_counter']);
$container->set('shared.cpu_cores', fn () => $shared['cpu_cores']);

// Logger
$container->set(StdoutLogger::class, function ($c) {
    $config = $c->get(Config::class);
    $logLevel = $config->get('LOG_LEVEL', 'info');

    return new StdoutLogger($logLevel);
});

// Lazy Services
$container->set(ConnectionPool::class, fn ($c) => new ConnectionPool($c->get('shared.table.connections')));
$container->set(TaskCounter::class, fn ($c) => new SwooleAtomicCounter($c->get('shared.atomic.tasks')));
$container->set(MessageHub::class, fn ($c) => new MessageHub($c->get(Server::class), $c->get(ConnectionPool::class)));

$container->set(TaskService::class, fn ($c) => new TaskService(
    new SwooleChannelSemaphore($config->getInt('TASK_SEMAPHORE_MAX_LIMIT', 10)),
    new WsEventBroadcaster($c->get(MessageHub::class)),
    new DemoDelayStrategy(),
    $c->get(TaskCounter::class),
    $c->get(StdoutLogger::class),
    $config
));

$container->set(EventHandler::class, function ($c) use ($config) {
    $taskController = new TaskController($c->get(TaskService::class), $c->get(MessageHub::class));

    return new EventHandler(
        new Router($taskController),
        $c->get(ConnectionPool::class),
        $c->get(TaskCounter::class),
        $c->get(TaskService::class),
        $config,
    );
});

// Worker Lifecycle
$server->on('WorkerStart', function ($server, $workerId) use ($container, $config) {
    Process::signal(SIGINT, function () use ($server) {
        // Swoole will call onWorkerStop itself
        $server->stop();
    });

    try {
        $taskService = $container->get(TaskService::class);

        // Start task consumers inside the worker
        Co::create(function () use ($taskService, $server, $workerId) {
            $taskService->startWorker($server, $workerId);
        });
    } catch (\Throwable $e) {
        echo "!!! ERROR IN WORKER #$workerId: " . $e->getMessage() . "\n";
    }
});

// Graceful shutdown
$server->on('WorkerStop', function ($server, $workerId) use ($container) {
    $taskService = $container->get(TaskService::class);
    $taskCounter = $container->get(TaskCounter::class);
    $config = $container->get(Config::class);

    // Close the queue - stop taking new jobs from the channel
    $taskService->shutdown();

    $timeout = (float) $config->get('GRACEFUL_SHUTDOWN_TIMEOUT_SEC', 30);
    $start = microtime(true);

    // Poll the atomic counter until it's zero or we hit the timeout
    while ($taskCounter->get() > 0 && (microtime(true) - $start) < $timeout) {
        usleep(50000);
    }

    $duration = round(microtime(true) - $start, 2);
    echo ">>> [System] Worker #$workerId stopped after {$duration}s. Active tasks: " . $taskCounter->get() . "\n";
});

// Lazy Event Handlers
$server->on('request', fn ($req, $res) => $container->get(EventHandler::class)->onRequest($req, $res));
$server->on('open', fn ($s, $req) => $container->get(EventHandler::class)->onOpen($s, $req));
$server->on('message', fn ($s, $f) => $container->get(EventHandler::class)->onMessage($s, $f));
$server->on('close', fn ($s, $fd) => $container->get(EventHandler::class)->onClose($s, $fd));

// IPC for Global Broadcast
$server->on('pipeMessage', function ($server, $srcWorkerId, $message) use ($container) {
    $data = json_decode($message, true);

    if (is_array($data) && isset($data['action']) && $data['action'] === 'broadcast_ws') {
        $container->get(MessageHub::class)->localBroadcast($data['payload']);
    }
});

// On start
$server->on('start', function (Server $server) use ($config) {
    echo "========================================\n";
    echo "Atomic Flow Server: Ready to process\n";
    echo "URL: http://" . $config->get('SERVER_HOST') . ":" . $config->get('SERVER_PORT') . "\n";
    echo "========================================\n";
});

$server->start();
