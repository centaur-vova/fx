<?php

declare(strict_types=1);

namespace App\Server;

use App\Config;
use App\Container;
use App\Contracts\Monitoring\TaskCounter;
use App\Contracts\Tasks\TaskDelayStrategy;
use App\Contracts\Tasks\TaskSemaphore;
use App\Controllers\TaskController;
use App\Router;
use App\Services\Tasks\Semaphores\{GlobalSharedSemaphore, WorkerLocalSemaphore};
use App\Services\Tasks\Strategies\DemoDelayStrategy;
use App\Services\Tasks\TaskService;
use App\Support\Monitoring\SwooleAtomicCounter;
use App\Support\StdoutLogger;
use App\WebSocket\ConnectionPool;
use App\WebSocket\MessageHub;
use App\WebSocket\WsEventBroadcaster;
use Psr\Log\LoggerInterface;
use Swoole\Atomic;
use Swoole\Coroutine as Co;
use Swoole\WebSocket\Server;

class Kernel
{
    private Container $container;
    private Server $server;
    private Config $config;

    public function __construct(private string $basePath)
    {
        // Load config
        $this->config = Config::fromEnv($this->basePath);

        // Create Server instance
        $this->server = new Server(
            $this->config->get('SERVER_HOST', '0.0.0.0'),
            $this->config->getInt('SERVER_PORT', 9501)
        );

        // Server config
        $this->server->set([
            'worker_num' => $this->config->getInt('SERVER_WORKER_NUM', 4),
            'dispatch_mode' => $this->config->getInt('SERVER_DISPATCH_MODE', 2),
            'enable_coroutine' => true,
            'socket_buffer_size' => $this->config->getInt('SOCKET_BUFFER_SIZE_MB', 64) * 1024 * 1024,

            // Static files
            'enable_static_handler' => true,
            'document_root' => rtrim($this->basePath, '/') . '/public',
            'http_compression' => true,
            'http_index_files' => ['index.html'],
        ]);

        $this->container = $this->bootContainer();
    }

    public function run(): void
    {
        $this->registerEvents();
        $this->server->start();
    }

    private function bootContainer(): Container
    {
        $c = new Container();

        // Global shared instances
        $c->set(Config::class, fn () => $this->config);
        $c->set(Server::class, fn () => $this->server);

        // WebSocket Connections storage
        $tableSize = $this->config->getInt('WS_TABLE_SIZE', 1024);
        $connectionsTable = ConnectionPool::configureAndCreateTable($tableSize);

        // Task counter
        $tasksAtomic = new Atomic(0);

        // Available CPU cores
        $cpuCores = (int) shell_exec('nproc') ?: 1;

        /**
         * Pre-allocate Shared Memory Semaphores
         *
         * {@see \App\Services\Tasks\Semaphores\GlobalSharedSemaphore}
         */
        $maxSemaphoreLimit = $this->config->getInt('TASK_SEMAPHORE_MAX_LIMIT', 10);
        $semaphoreAtomics = [];
        for ($i = 1; $i <= $maxSemaphoreLimit; $i++) {
            // Each index represents a specific max_concurrent limit
            $semaphoreAtomics[$i] = new \Swoole\Atomic(0);
        }

        // Register shared infrastructure primitives
        $c->set('shared.table.connections', fn () => $connectionsTable);
        $c->set('shared.atomic.tasks', fn () => $tasksAtomic);
        $c->set('shared.cpu_cores', fn () => $cpuCores);
        $c->set('shared.semaphores.atomics', fn () => $semaphoreAtomics);

        // Logger
        $c->set(StdoutLogger::class, function ($c) {
            $config = $c->get(Config::class);
            $logLevel = $config->get('LOG_LEVEL', 'info');

            return new StdoutLogger($logLevel);
        });
        $c->set(LoggerInterface::class, fn ($c) => $c->get(StdoutLogger::class));

        // Services
        $c->set(ConnectionPool::class, fn ($c) => new ConnectionPool($c->get('shared.table.connections')));
        $c->set(TaskCounter::class, fn ($c) => new SwooleAtomicCounter($c->get('shared.atomic.tasks')));
        $c->set(MessageHub::class, fn ($c) => new MessageHub($c->get(Server::class), $c->get(ConnectionPool::class)));
        $c->set(TaskSemaphore::class, function ($c) {
            // TODO: Add the abity to switch semaphore implementation
            // return new WorkerLocalSemaphore($c->get(Config::class)->getInt('TASK_SEMAPHORE_MAX_LIMIT', 10));
            return new GlobalSharedSemaphore($c->get('shared.semaphores.atomics'));
        });
        $c->set(WsEventBroadcaster::class, fn ($c) => new WsEventBroadcaster($c->get(MessageHub::class)));
        $c->set(TaskDelayStrategy::class, fn ($c) => new DemoDelayStrategy());

        $c->set(TaskService::class, fn ($c) => new TaskService(
            $c->get(TaskSemaphore::class),
            $c->get(WsEventBroadcaster::class),
            $c->get(TaskDelayStrategy::class),
            $c->get(TaskCounter::class),
            $c->get(StdoutLogger::class),
            $c->get(Config::class),
        ));

        $c->set(EventHandler::class, function ($c) {
            $taskController = new TaskController($c->get(TaskService::class), $c->get(MessageHub::class));
            $router = new Router($taskController);

            return new EventHandler(
                $router,
                $c->get(ConnectionPool::class),
                $c->get(TaskCounter::class),
                $c->get(TaskService::class),
                $c->get(Config::class),
            );
        });

        return $c;
    }

    private function registerEvents(): void
    {
        // Worker Lifecycle
        $this->server->on('WorkerStart', function ($server, $workerId) {
            try {
                $taskService = $this->container->get(TaskService::class);

                // Start task consumers inside the worker
                Co::create(function () use ($taskService, $server, $workerId) {
                    $taskService->startWorker($server, $workerId);
                });
            } catch (\Throwable $e) {
                $this
                    ->container
                    ->get(LoggerInterface::class)
                    ->error("Worker start failed", ['e' => $e->getMessage()]);
            }
        });

        // Graceful shutdown
        $this->server->on('WorkerStop', function ($server, $workerId) {
            $taskService = $this->container->get(TaskService::class);
            $taskCounter = $this->container->get(TaskCounter::class);
            $config = $this->container->get(Config::class);

            // Close the queue - stop taking new jobs from the channel
            $taskService->shutdown();

            $timeout = (float) $config->get('GRACEFUL_SHUTDOWN_TIMEOUT_SEC', 30);
            $start = microtime(true);

            // Poll the atomic counter until it's zero or we hit the timeout
            while ($taskCounter->get() > 0 && (microtime(true) - $start) < $timeout) {
                usleep(50000);
            }

            $duration = round(microtime(true) - $start, 2);
            $this
                ->container
                ->get(LoggerInterface::class)
                ->info("[System] Worker #$workerId stopped after {$duration}s. Active tasks: " . $taskCounter->get());
        });

        // WebSocket Event Handlers
        $this->server->on('request', fn ($req, $res) => $this->container->get(EventHandler::class)->onRequest($req, $res));
        $this->server->on('open', fn ($s, $req) => $this->container->get(EventHandler::class)->onOpen($s, $req));
        $this->server->on('message', fn ($s, $f) => $this->container->get(EventHandler::class)->onMessage($s, $f));
        $this->server->on('close', fn ($s, $fd) => $this->container->get(EventHandler::class)->onClose($s, $fd));

        // IPC for Global Broadcast
        $this->server->on('pipeMessage', function ($server, $srcWorkerId, $message) {
            $data = json_decode($message, true);

            if (is_array($data) && isset($data['action']) && $data['action'] === 'broadcast_ws') {
                $this->container->get(MessageHub::class)->localBroadcast($data['payload']);
            }
        });

        // On start
        $this->server->on('start', function (Server $server) {
            $host = $this->config->get('SERVER_HOST', '0.0.0.0');
            $port = $this->config->get('SERVER_PORT', 9501);

            $this
                ->container
                ->get(LoggerInterface::class)
                ->info("Atomic Flow Server: Ready to process", [
                    'url' => "http://{$host}:{$port}",
                    'version' => '1.0.0',
                    'worker_num' => $server->setting['worker_num'] ?? 0,
                ]);
        });
    }
}
