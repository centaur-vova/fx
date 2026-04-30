<?php

declare(strict_types=1);

namespace App\Server;

use App\ConfigLoader;
use App\Contract\Messaging\MessageSerializer;
use App\Contract\Task\TaskQueue;
use App\Contract\Task\TaskSemaphore;
use App\Controller\TaskController;
use App\DTO\Task\TaskExecutionPayload;
use App\Router;
use App\Service\Messaging\MappedMessageSerializer;
use App\Service\Provider\App\AppServiceProvider;
use App\Service\Provider\App\RateLimiterServiceProvider;
use App\Service\Provider\Contract\Bootable;
use App\Service\Provider\Contract\WorkerStartAware;
use App\Service\Provider\Nats\NatsBroadcasterServiceProvider;
use App\Service\Provider\Nats\NatsClientProvider;
use App\Service\Provider\Nats\NatsTaskQueueServiceProvider;
use App\Service\Provider\Task\TaskServiceProvider;
use App\Service\RateLimiter\RateLimiterService;
use App\Service\Task\Semaphore\GlobalSharedSemaphore;
use App\Service\Task\TaskService;
use App\Support\StdoutLogger;

use function DI\autowire;

use DI\Container;
use DI\ContainerBuilder;

use function DI\create;
use function DI\get;

use Psr\Log\LoggerInterface;
use Swoole\Atomic;
use Swoole\Coroutine as Co;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Server\Task;
use Throwable;

class Kernel
{
    private readonly Container $container;
    private readonly Server $server;
    private readonly Options $options;
    private readonly LoggerInterface $logger;

    private const array PROVIDERS = [
        AppServiceProvider::class,
        NatsClientProvider::class,
        NatsBroadcasterServiceProvider::class,
        NatsTaskQueueServiceProvider::class,
        TaskServiceProvider::class,
        RateLimiterServiceProvider::class,
    ];

    public function __construct(private readonly string $basePath)
    {
        // Load config from .env
        $loader = ConfigLoader::fromEnv($this->basePath);

        // System settings
        $workerNum = $loader->getInt('SERVER_WORKER_NUM', 4);

        /** @var array<string, array{max_attempts: int, ttl: int}> $rateLimiters */
        $rateLimiters = $loader->getArray('RATE_LIMITERS', []);

        // Options
        $options = new Options(
            serverHost:           $loader->getString('SERVER_HOST', '0.0.0.0'),
            logLevel:             $loader->getString('LOG_LEVEL', 'info'),
            serverPort:           $loader->getInt('SERVER_PORT', 9501),
            dispatchMode:         $loader->getInt('SERVER_DISPATCH_MODE', 2),
            socketBufferMb:       $loader->getInt('SOCKET_BUFFER_SIZE_MB', 64),
            taskMaxBatchSize:     $loader->getInt('TASK_MAX_BATCH_SIZE', 5000),
            taskSemaphoreLimit:   $loader->getInt('TASK_SEMAPHORE_MAX_LIMIT', 10),
            taskLockTimeoutSec:   $loader->getFloat('TASK_LOCK_TIMEOUT_SEC', 4.0),
            taskRetryDelaySec:    $loader->getInt('TASK_RETRY_DELAY_SEC', 5),
            taskMaxRetries:       $loader->getInt('TASK_MAX_RETRIES', 3),
            metricsIntervalMs:    $loader->getInt('METRICS_UPDATE_INTERVAL_MS', 1000),
            shutdownTimeoutSec:   $loader->getInt('GRACEFUL_SHUTDOWN_TIMEOUT_SEC', 5),
            natsHost:             $loader->getString('NATS_HOST', 'nutz'),
            natsPort:             $loader->getInt('NATS_PORT', 4222),
            natsToken:            $loader->getString('NATS_TOKEN', ''),
            workerNum:            $workerNum,
            // Cache
            cacheStorageDriver:   $loader->getString('CACHE_STORAGE_DRIVER', 'swoole_table'),
            cacheDefaultTtlSec:   $loader->getInt('CACHE_DEFAULT_TTL_SEC', 60),
            cacheMaxSize:         $loader->getInt('CACHE_MAX_SIZE', 131072),
            cacheAutoCleanSec:    $loader->getInt('CACHE_AUTO_CLEAN_SEC', 60),
            cacheValueMaxSize:    $loader->getInt('CACHE_VALUE_MAX_SIZE', 256), // swoole only
            // Queue
            queueCapacity:        $loader->getInt('QUEUE_CAPACITY', 1000),
            queuePrefetchBatch:   $loader->getInt('QUEUE_PREFETCH_BATCH', 100),
            // NATS & broadcast
            natsAckWaitMs:        $loader->getInt('NATS_ACK_WAIT_MS', 30000),
            natsTimeoutSec:       $loader->getFloat('NATS_TIMEOUT_SEC', 1.0),
            natsPingIntervalSec:  $loader->getInt('NATS_PING_INTERVAL_SEC', 10),
            natsWorkerPingIntervalSec: $loader->getInt('NATS_WORKER_PING_INTERVAL_SEC', 5),
            // Queue/streams
            broadcastSubject:     $loader->getString('NATS_SUBJECT_BROADCAST', 'ws.broadcast'),
            taskQueueSubject:     $loader->getString('NATS_SUBJECT_TASKS', 'task.queue'),
            taskQueueConsumer:    $loader->getString('NATS_CONSUMER_TASKS', 'php-task-consumers'),
            taskQueueStream:      $loader->getString('NATS_STREAM_TASKS', 'tasks'),
            // Task Meta (KV)
            taskMetaCacheSize:    $loader->getInt('TASK_META_CACHE_SIZE', 65536),
            taskMetaTtlSec:       $loader->getInt('TASK_META_TTL_SEC', 10),
            // Misc
            rateLimiters:         $rateLimiters,
        );

        // Assign options to object state
        $this->options = $options;

        // Create Server instance
        $this->server = new Server($options->serverHost, $options->serverPort);

        // Server settings
        $this->server->set([
            // Hearthbeat & TCP
            'heartbeat_check_interval' => 30,
            'heartbeat_idle_time' => 60,
            'open_tcp_keepalive' => true,
            'tcp_keepidle' => 60,
            'tcp_keepinterval' => 10,

            // Workers
            'worker_num' => $options->workerNum,
            'task_worker_num' => $options->workerNum, // same as Server's worker_num

            // System
            'dispatch_mode' => $options->dispatchMode,
            'socket_buffer_size' => $options->socketBufferMb * 1024 * 1024,

            // Enable coroutines
            'enable_coroutine' => true,
            'task_enable_coroutine' => true,
            'task_use_object' => true,

            // Static files & HTTP
            'enable_static_handler' => true,
            'document_root' => rtrim($this->basePath, '/') . '/public',
            'http_compression' => true,
            'http_compression_level' => 6,
            'http_index_files' => ['index.html'],
        ]);

        $this->container = $this->bootContainer();

        /** @var LoggerInterface $logger */
        $logger = $this->container->get(LoggerInterface::class);
        $this->logger = $logger;

        // Boot providers
        $this->bootProviders();
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
        $builder = new ContainerBuilder()
            ->useAutowiring(true)
            ->addDefinitions([
                Server::class => $server,
                Options::class => $options,

                // Config options (explicit)
                'options.task_max_retries' => $options->taskMaxRetries,
                'options.task_retry_delay_sec' => $options->taskRetryDelaySec,
                'options.task_lock_timeout_sec' => $options->taskLockTimeoutSec,
                'options.task_max_batch_size' => $options->taskMaxBatchSize,
                'options.task_semaphore_limit' => $options->taskSemaphoreLimit,

                // Queue
                'options.queue_capacity' => $options->queueCapacity,

                // Channels & Jet streams
                'options.broadcast_subject' => $options->broadcastSubject,
                'options.task_queue_subject' => $options->taskQueueSubject,
                'options.task_queue_stream' => $options->taskQueueStream,
                'options.nats_ack_wait_ms' => $options->natsAckWaitMs,

                'shared.atomic.tasks' => $tasksAtomic,
                'shared.semaphores.atomics' => $semaphoreAtomics,

                // Logger
                StdoutLogger::class => create()
                    ->constructor(fn (Options $opt) => $opt->logLevel),
                LoggerInterface::class => get(StdoutLogger::class),

                // Semaphore
                TaskSemaphore::class => create(GlobalSharedSemaphore::class)
                    ->constructor(get('shared.semaphores.atomics')),

                // Task Service
                TaskService::class => autowire()
                    ->constructorParameter('broadcastSubject', get('options.broadcast_subject'))
                    ->constructorParameter('maxRetries', get('options.task_max_retries'))
                    ->constructorParameter('retryDelaySec', get('options.task_retry_delay_sec'))
                    ->constructorParameter('lockTimeoutSec', get('options.task_lock_timeout_sec')),

                TaskController::class => create()
                    ->constructor(
                        get(TaskService::class),
                        get(TaskQueue::class),
                        get(RateLimiterService::class),
                        get(LoggerInterface::class),
                        get('options.task_max_batch_size'),
                        get('options.task_semaphore_limit'),
                    ),

                Router::class => autowire(Router::class),

                // Serializer
                MessageSerializer::class => autowire(MappedMessageSerializer::class),
           ]);

        // Custom providers
        foreach (self::PROVIDERS as $providerClass) {
            $builder->addDefinitions(new $providerClass()->register($builder));
        }

        // Build DI container
        $container = $builder->build();

        return $container;
    }

    private function bootProviders(): void
    {
        foreach (self::PROVIDERS as $providerClass) {
            $provider = $this->container->get($providerClass);

            if ($provider instanceof Bootable) {
                $provider->boot($this->container);
            }
        }
    }

    private function registerEvents(): void
    {
        // Task Lifecycle
        $this->server->on('task', function (Server $server, Task $task): void {
            /** @var int $workerId */
            $workerId = $server->worker_id;

            // Create a coroutine so this Task Worker can handle multiple concurrent tasks
            Co::create(function () use ($server, $task, $workerId): void {
                try {
                    if ($task->data instanceof TaskExecutionPayload) {
                        /** @var TaskService $taskService */
                        $taskService = $this->container->get(TaskService::class);

                        $taskService->processTask($task->data, $workerId);
                        $task->finish(true);
                    }

                } catch (Throwable $e) {
                    $this->logger->error('Task execution failed', [
                        'error' => $e->getMessage(),
                        'worker_id' => $server->worker_id,
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $task->finish(false);
                }
            });
        });

        // Required for task completion
        $this->server->on('finish', function (Server $server, $taskId, $data): void {
            // Optional: Log task completion from worker pool
        });

        $this->server->on('WorkerStart', function (Server $server, int $workerId): void {
            // Replace server instance in DI
            $this->container->set(Server::class, $server);

            // Iterate over providers list & execute :)
            foreach (self::PROVIDERS as $providerClass) {
                $provider = $this->container->get($providerClass);

                if ($provider instanceof WorkerStartAware) {
                    $provider->onWorkerStart($this->container, $server, $workerId);
                }
            }
        });

        // Graceful shutdown
        $this->server->on('WorkerStop', function ($server, int $workerId): void {
            $start = microtime(true);

            /**
             * Wait for active tasks to finish.
             * Using Co::sleep (if in coroutine context) or usleep for safe polling.
             */
            /*
            // TODO: REFACTOR
            while ($taskCounter->get() > 0 && (microtime(true) - $start) < $timeout) {
                // Check if we can use non-blocking sleep
                if (Co::getuid() > 0) {
                    Co::sleep(0.05);
                } else {
                    usleep(50000);
                }
            }
            */

            $duration = round(microtime(true) - $start, 2);

            $this->logger->info("[System] Worker #$workerId stopped after {$duration}s.");
        });

        // Request handling
        $this->server->on(
            'request',
            function (Request $req, Response $res): void {
                /** @var Router $router */
                $router = $this->container->get(Router::class);
                $router->handle($req, $res, $this->server);
            }
        );

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
                    " └──────────────────────────────────────────┘\n" .
                    " » STATUS : READY TO FLOW\n" .
                    " » LISTEN : http://{$host}:{$port}\n"
                );
        });
    }
}
