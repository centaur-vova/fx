<?php

declare(strict_types=1);

namespace App\Server;

use App\ConfigLoader;
use App\Container;
use App\Contracts\Monitoring\TaskCounter;
use App\Contracts\Tasks\TaskDelayStrategy;
use App\Contracts\Tasks\TaskSemaphore;
use App\Controllers\TaskController;
use App\DTO\WebSockets\Messages\WelcomeMessage;
use App\Router;
use App\Services\Monitoring\SystemMonitor;
use App\Services\Tasks\ProcessorFactory;
use App\Services\Tasks\Semaphores\GlobalSharedSemaphore;
use App\Services\Tasks\Strategies\DemoDelayStrategy;
use App\Services\Tasks\TaskService;
use App\Support\Monitoring\SwooleAtomicCounter;
use App\Support\StdoutLogger;
use App\WebSocket\ConnectionPool;
use App\WebSocket\MessageHub;
use App\WebSocket\WsEventBroadcaster;
use Fidry\CpuCoreCounter\CpuCoreCounter;
use Psr\Log\LoggerInterface;
use Swoole\Atomic;
use Swoole\Coroutine as Co;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server\Task;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class Kernel
{
    private readonly Container $container;
    private readonly Server $server;
    private readonly Options $options;

    public function __construct(private readonly string $basePath)
    {
        // Load config from .env
        $loader = ConfigLoader::fromEnv($this->basePath);

        /**
         * Detect available CPU cores for worker scaling.
         * PHP 8.4 fluent instantiation style.
         */
        $cpuCores = max(1, new CpuCoreCounter()->getCount());

        // App version
        $versionPath = __DIR__ . '/../../version.php';
        $appVersion = file_exists($versionPath) ? require $versionPath : 'local';

        // System settings
        $workerNum = $loader->getInt('SERVER_WORKER_NUM', 4);
        $queueCapacity = $loader->getInt('QUEUE_CAPACITY', 10000);

        // Message to send to clients onopen
        $welcomeMessage = new WelcomeMessage(
            workerNum: $workerNum,
            cpuCores: $cpuCores,
            queueCapacity: $queueCapacity,
            appVersion: $appVersion,
        );

        // Options
        $options = new Options(
            appVersion:         (string) $appVersion,
            serverHost:         $loader->getString('SERVER_HOST', '0.0.0.0'),
            logLevel:           $loader->getString('LOG_LEVEL', 'info'),
            serverPort:         $loader->getInt('SERVER_PORT', 9501),
            dispatchMode:       $loader->getInt('SERVER_DISPATCH_MODE', 2),
            socketBufferMb:     $loader->getInt('SOCKET_BUFFER_SIZE_MB', 64),
            wsTableSize:        $loader->getInt('WS_TABLE_SIZE', 1024),
            workerConcurrency:  $loader->getInt('WORKER_CONCURRENCY', 10),
            taskSemaphoreLimit: $loader->getInt('TASK_SEMAPHORE_MAX_LIMIT', 10),
            taskLockTimeoutSec: $loader->getFloat('TASK_LOCK_TIMEOUT_SEC', 4.0),
            taskRetryDelaySec:  $loader->getInt('TASK_RETRY_DELAY_SEC', 5),
            taskMaxRetries:     $loader->getInt('TASK_MAX_RETRIES', 3),
            metricsIntervalMs:  $loader->getInt('METRICS_UPDATE_INTERVAL_MS', 1000),
            shutdownTimeoutSec: $loader->getInt('GRACEFUL_SHUTDOWN_TIMEOUT_SEC', 5),
            stressMinTaskNum:   $loader->getInt('STRESS_MIN_TASK_NUM', 1000),
            queueCapacity:      $queueCapacity,
            workerNum:          $workerNum,
            cpuCores:           $cpuCores,
            welcomeMessage:     $welcomeMessage,
        );

        // Assign options to object state
        $this->options = $options;

        // Create Server instance
        $this->server = new Server($options->serverHost, $options->serverPort);

        // Server settings
        $this->server->set([
            // Workers
            'worker_num' => $options->workerNum,
            'task_worker_num' => $options->workerNum, // same as Server's worker_num

            // System
            'dispatch_mode' => $options->dispatchMode,
            'socket_buffer_size' => $options->socketBufferMb * 1024 * 1024,

            // Enable coroutines
            'enable_coroutine' => true,
            'task_enable_coroutine' => true,

            // Static files & HTTP
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
        /**
         * Create local references to prevent $this-binding in container closures.
         * This allows using 'static fn' for better performance and memory isolation.
         */
        $server = $this->server;
        $options = $this->options;

        // Create container
        $c = new Container();

        // Global shared instances
        $c->set(Server::class, static fn () => $server);
        $c->set(Options::class, static fn () => $options);

        // WebSocket Connections storage
        $connectionsTable = ConnectionPool::configureAndCreateTable($options->wsTableSize);

        // Task counter
        $tasksAtomic = new Atomic(0);

        /**
         * Pre-allocate Shared Memory Semaphores.
         * The index $i represents the 'max_concurrency' level.
         *
         * Each Atomic object is a shared counter across all Swoole workers.
         * @see GlobalSharedSemaphore
         */
        $semaphoreLimit = max(1, $options->taskSemaphoreLimit);
        $semaphoreAtomics = [];

        for ($i = 1; $i <= $semaphoreLimit; $i++) {
            // Each index represents a specific max_concurrent limit
            $semaphoreAtomics[$i] = new Atomic(0);
        }

        // Register shared infrastructure primitives
        $c->set('shared.table.connections', static fn () => $connectionsTable);
        $c->set('shared.atomic.tasks', static fn () => $tasksAtomic);
        $c->set('shared.semaphores.atomics', static fn () => $semaphoreAtomics);

        // Logger
        $c->set(StdoutLogger::class, static fn ($c) => new StdoutLogger($options->logLevel));
        $c->set(LoggerInterface::class, static fn ($c) => $c->get(StdoutLogger::class));

        // Services
        $c->set(ProcessorFactory::class, static fn ($c) => new ProcessorFactory());
        $c->set(ConnectionPool::class, static fn ($c) => new ConnectionPool($c->get('shared.table.connections')));
        $c->set(TaskCounter::class, static fn ($c) => new SwooleAtomicCounter($c->get('shared.atomic.tasks')));
        $c->set(TaskSemaphore::class, static fn ($c) => new GlobalSharedSemaphore($c->get('shared.semaphores.atomics')));
        $c->set(MessageHub::class, static fn ($c) => new MessageHub($c->get(Server::class), $c->get(ConnectionPool::class)));
        $c->set(
            SystemMonitor::class,
            static fn ($c) => new SystemMonitor($c->get(ConnectionPool::class))
        );

        $c->set(WsEventBroadcaster::class, static fn ($c) => new WsEventBroadcaster($c->get(MessageHub::class)));
        $c->set(TaskDelayStrategy::class, static fn ($c) => new DemoDelayStrategy());

        $c->set(TaskService::class, static fn ($c) => new TaskService(
            server: $c->get(Server::class),
            semaphore: $c->get(TaskSemaphore::class),
            broadcaster: $c->get(WsEventBroadcaster::class),
            delayStrategy: $c->get(TaskDelayStrategy::class),
            taskCounter: $c->get(TaskCounter::class),
            processorFactory: $c->get(ProcessorFactory::class),
            logger: $c->get(StdoutLogger::class),
            queueCapacity: $options->queueCapacity,
            maxRetries: $options->taskMaxRetries,
            retryDelaySec: $options->taskRetryDelaySec,
            lockTimeoutSec: $options->taskLockTimeoutSec,
        ));

        $c->set(EventHandler::class, static function ($c) use ($options): EventHandler {
            $taskController = new TaskController(
                taskService: $c->get(TaskService::class),
                wsHub: $c->get(MessageHub::class),
                appVersion: $options->appVersion,
                stressMinTaskNum: $options->stressMinTaskNum,
            );
            $router = new Router($taskController);

            return new EventHandler(
                $router,
                $c->get(ConnectionPool::class),
                $c->get(SystemMonitor::class),
                $c->get(LoggerInterface::class),
                $c->get(TaskService::class),
                $options->welcomeMessage,
                $options->metricsIntervalMs,
            );
        });

        return $c;
    }

    private function registerEvents(): void
    {
        // Task Lifecycle
        $this->server->on('task', function (Server $server, Task $task): void {
            // Create a coroutine so this Task Worker can handle multiple concurrent tasks
            Co::create(function () use ($server, $task): void {
                try {
                    /** @var TaskService $taskService */
                    $taskService = $this->container->get(TaskService::class);
                    $data = $task->data;

                    // Execute task logic. Retries are now handled internally via Co::sleep
                    $taskService->processTask(
                        $server->worker_id,
                        $data['id'],
                        $data['mc'],
                        $data['mode'],
                    );

                    $task->finish(['status' => 'ok']);
                } catch (\Throwable $e) {
                    $this->container->get(LoggerInterface::class)->error('Task execution failed', [
                        'error' => $e->getMessage(),
                        'worker_id' => $server->worker_id,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            });
        });

        // Required for task completion
        $this->server->on('finish', function ($server, $taskId, $data): void {
            // Optional: Log task completion from worker pool
        });

        // Worker Lifecycle
        $this->server->on('WorkerStart', function ($server, $workerId): void {
            // Re-bind the server instance to the current worker context
            $this->container->set(Server::class, static fn () => $server);

            /**
             * IMPORTANT: Clear cached singletons to ensure each worker
             * starts with fresh, isolated service instances.
             */
            $this->container->forget(EventHandler::class);
            $this->container->forget(TaskService::class);
            $this->container->forget(SystemMonitor::class);
            $this->container->forget(MessageHub::class);
        });

        // Graceful shutdown
        $this->server->on('WorkerStop', function ($server, $workerId): void {
            $taskCounter = $this->container->get(TaskCounter::class);
            $timeout = $this->options->shutdownTimeoutSec;
            $start = microtime(true);

            /**
             * Wait for active tasks to finish.
             * Using Co::sleep (if in coroutine context) or usleep for safe polling.
             */
            while ($taskCounter->get() > 0 && (microtime(true) - $start) < $timeout) {
                // Check if we can use non-blocking sleep
                if (Co::getuid() > 0) {
                    Co::sleep(0.05);
                } else {
                    usleep(50000);
                }
            }

            $duration = round(microtime(true) - $start, 2);
            $this
                ->container
                ->get(LoggerInterface::class)
                ->info("[System] Worker #$workerId stopped after {$duration}s. Active tasks: " . $taskCounter->get());
        });

        // WebSocket Event Handlers
        $this->server->on('request', fn (Request $req, Response $res) => $this->container->get(EventHandler::class)->onRequest($req, $res));
        $this->server->on('open', fn (Server $s, Request $req) => $this->container->get(EventHandler::class)->onOpen($s, $req));
        $this->server->on('message', fn (Server $s, Frame $f) => $this->container->get(EventHandler::class)->onMessage($s, $f));
        $this->server->on('close', fn (Server $s, int $fd) => $this->container->get(EventHandler::class)->onClose($s, $fd));

        // IPC for Global Broadcast
        $this->server->on('pipeMessage', function ($server, $srcWorkerId, $message): void {
            $data = json_decode($message, true);

            if (is_array($data) && isset($data['action']) && $data['action'] === 'broadcast_ws') {
                $this->container->get(MessageHub::class)->localBroadcast($data['payload']);
            }
        });

        // On start
        $this->server->on('start', function (Server $server): void {
            $host = $this->options->serverHost;
            $port = $this->options->serverPort;

            $this
                ->container
                ->get(LoggerInterface::class)
                ->info(
                    "\n" .
                    " ┌──────────────────────────────────────────┐\n" .
                    " │  FAST.AF :: ATOMIC PIPELINE ENGINE       │\n" .
                    " │  NODE ID : root@l3373.xyz                │\n" .
                    ' │  KERNEL  : ' . str_pad($this->options->appVersion, 30) . "│\n" .
                    " └──────────────────────────────────────────┘\n" .
                    " » STATUS : READY TO FLOW\n" .
                    " » LISTEN : http://{$host}:{$port}\n"
                );
        });
    }
}
