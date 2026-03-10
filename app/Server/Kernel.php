<?php

declare(strict_types=1);

namespace App\Server;

use App\ConfigLoader;
use App\Contract\Monitoring\TaskCounter;
use App\Contract\Support\EventBus;
use App\Contract\Task\TaskDelayStrategy;
use App\Contract\Task\TaskSemaphore;
use App\Controller\TaskController;
use App\DTO\WebSocket\Event\TaskStatusChangedEvent;
use App\DTO\WebSocket\Message\InternalEnvelope;
use App\DTO\WebSocket\Message\WelcomeMessage;
use App\Router;
use App\Service\Monitoring\SystemMonitor;
use App\Service\Task\Processor\ProcessorFactory;
use App\Service\Task\Semaphore\GlobalSharedSemaphore;
use App\Service\Task\Strategy\DemoDelayStrategy;
use App\Service\Task\TaskService;
use App\Support\InternalBus;
use App\Support\Monitoring\SwooleAtomicCounter;
use App\Support\StdoutLogger;
use App\WebSocket\ConnectionPool;
use App\WebSocket\MessageHub;
use DI\Container;
use DI\ContainerBuilder;

use function DI\create;
use function DI\factory;
use function DI\get;

use Fidry\CpuCoreCounter\CpuCoreCounter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
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
    private readonly LoggerInterface $logger;

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
            taskMaxBatchSize:   $loader->getInt('TASK_MAX_BATCH_SIZE', 5000),
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

        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);
        $this->logger = $logger;
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

        /**
         * DI container setup
         */
        $c = new ContainerBuilder()
            ->useAutowiring(true)
            ->addDefinitions([
                Server::class => $server,
                Options::class => $options,

                // Config options (explicit)
                'options.app_version' => $options->appVersion,
                'options.queue_capacity' => $options->queueCapacity,
                'options.task_max_retries' => $options->taskMaxRetries,
                'options.task_retry_delay_sec' => $options->taskRetryDelaySec,
                'options.task_lock_timeout_sec' => $options->taskLockTimeoutSec,
                'options.stress_min_task_num' => $options->stressMinTaskNum,
                'options.task_max_batch_size' => $options->taskMaxBatchSize,
                'options.task_semaphore_limit' => $options->taskSemaphoreLimit,

                // Передаем уже созданную таблицу (Shared Memory)
                'shared.table.connections' => $connectionsTable,
                'shared.atomic.tasks' => $tasksAtomic,
                'shared.semaphores.atomics' => $semaphoreAtomics,

                // Logger
                StdoutLogger::class => create()
                    ->constructor(fn (Options $opt) => $opt->logLevel),
                LoggerInterface::class => create(StdoutLogger::class),

                // Services
                MessageHub::class => create()
                    ->constructor(
                        get(Server::class),
                        get(ConnectionPool::class),
                    )
                    ->lazy(),

                // ConnectionPool
                ConnectionPool::class => create()->constructor(get('shared.table.connections')),

                // Misc
                TaskCounter::class => create(SwooleAtomicCounter::class)
                    ->constructor(get('shared.atomic.tasks')),
                TaskSemaphore::class => create(GlobalSharedSemaphore::class)
                    ->constructor(get('shared.semaphores.atomics')),

                TaskDelayStrategy::class => create(DemoDelayStrategy::class),

                SystemMonitor::class => create(SystemMonitor::class)
                    ->constructor(get(ConnectionPool::class)),

                // EventBus
                EventBus::class => create(InternalBus::class),
                EventDispatcherInterface::class => get(EventBus::class),
                ListenerProviderInterface::class => get(EventBus::class),

                // Task Service
                TaskService::class => create()
                    ->constructor(
                        get(Server::class),
                        get(TaskSemaphore::class),
                        get(TaskDelayStrategy::class),
                        get(TaskCounter::class),
                        get(ProcessorFactory::class),
                        get(LoggerInterface::class),
                        get(EventBus::class),
                        get('options.queue_capacity'),
                        get('options.task_max_retries'),
                        get('options.task_retry_delay_sec'),
                        get('options.task_lock_timeout_sec'),
                    ),

                TaskController::class => create()
                    ->constructor(
                        get(TaskService::class),
                        get(MessageHub::class),
                        get('options.app_version'),
                        get('options.stress_min_task_num'),
                        get('options.task_max_batch_size'),
                        get('options.task_semaphore_limit'),
                    ),

                Router::class => create()
                    ->constructor(get(TaskController::class)),

                // Event Handler
                EventHandler::class => create()
                    ->constructor(
                        get(Router::class),
                        get(ConnectionPool::class),
                        get(SystemMonitor::class),
                        get(LoggerInterface::class),
                        get(TaskCounter::class),
                        factory(fn (Options $opt) => $opt->welcomeMessage),
                        factory(fn (Options $opt) => $opt->metricsIntervalMs)
                    ),
            ])
            ->build();

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
                    $this->logger->error('Task execution failed', [
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
            $this->container->set(Server::class, $server);

            /** @var EventBus $bus */
            $bus = $this->container->get(EventDispatcherInterface::class);
            /** @var MessageHub $messageHub */
            $messageHub = $this->container->get(MessageHub::class);

            $bus->listen(TaskStatusChangedEvent::class, static function ($event) use ($messageHub): void {
                $messageHub->broadcast(InternalEnvelope::wrap('status.changed', $event->update));
            });
        });

        // Graceful shutdown
        $this->server->on('WorkerStop', function ($server, $workerId): void {
            /** @var TaskCounter $taskCounter */
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

            $this->logger->info("[System] Worker #$workerId stopped after {$duration}s. Active tasks: " . $taskCounter->get());
        });

        // WebSocket Event Handlers
        $this->server->on(
            'request',
            function (Request $req, Response $res): void {
                /** @var EventHandler $handler */
                $handler = $this->container->get(EventHandler::class);
                $handler->onRequest($req, $res);
            }
        );
        $this->server->on(
            'open',
            function (Server $s, Request $req): void {
                /** @var EventHandler $handler */
                $handler = $this->container->get(EventHandler::class);
                $handler->onOpen($s, $req);
            }
        );
        $this->server->on(
            'message',
            function (Server $s, Frame $f): void {
                /** @var EventHandler $handler */
                $handler = $this->container->get(EventHandler::class);
                $handler->onMessage($s, $f);
            }
        );
        $this->server->on(
            'close',
            function (Server $s, int $fd): void {
                /** @var EventHandler $handler */
                $handler = $this->container->get(EventHandler::class);
                $handler->onClose($s, $fd);
            }
        );

        // IPC for Global Broadcast
        $this->server->on('pipeMessage', function ($server, $srcWorkerId, $message): void {
            $envelope = InternalEnvelope::fromSerialized((string) $message);

            if ($envelope === null) {
                return;
            }

            /** @var MessageHub $hub */
            $hub = $this->container->get(MessageHub::class);
            $hub->localBroadcast($envelope);
        });

        // On start
        $this->server->on('start', function (Server $server): void {
            $host = $this->options->serverHost;
            $port = $this->options->serverPort;

            $this
                ->logger
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
