<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\Storage\CacheStorage;
use App\Contract\Task\TaskQueue;
use App\DTO\Http\Request\CreateTasks;
use App\DTO\Http\Response\ApiResponse;
use App\DTO\Http\Response\HealthResponse;
use App\Exception\Http\InvalidTaskBatchException;
use App\Exception\Http\RateLimitExceededException;
use App\Service\RateLimiter\RateLimiterService;
use App\Service\Task\Processor\ProcessorFactory;
use App\Service\Task\TaskService;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Server;

class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskQueue $taskQueue,
        private readonly CacheStorage $cache,
        private readonly RateLimiterService $rateLimiterService,
        private readonly LoggerInterface $logger,
        private readonly int $taskMaxBatchSize,
        private readonly int $taskSemaphoreLimit,
    ) {
    }

    /**
     * @throws InvalidTaskBatchException
     * @throws RateLimitExceededException
     */
    public function createTasks(Request $request, CreateTasks $dto): ApiResponse
    {
        $this->rateLimit('create-tasks', $request);

        // Save timestamp when last createTasks request was sent
        $this->cache->set('task-last-created', (string) time(), 30 * 60); // Keep for 30 minutes

        // Validate DTO
        $dto->validate($this->taskMaxBatchSize, $this->taskSemaphoreLimit);

        // Random mode
        if ($dto->count === 0) {
            go(fn () => $this->taskService->createRandomBatches());
            return ApiResponse::ok('RAND mode initiated');
        }

        // Guess mode
        go(function () use ($dto): void {
            $mode = $dto->stressMode
                ? ProcessorFactory::MODE_STRESS
                : ProcessorFactory::MODE_OBSERVATION;

            $this->taskService->createBatch($dto->count, $dto->maxConcurrent, $dto->semaphoreDriver, $mode);
        });

        return ApiResponse::ok('Tasks queued');
    }

    /**
     * @throws RateLimitExceededException
     */
    public function purgeQueue(Request $request): ApiResponse
    {
        try {
            $this->rateLimit('purge-queue', $request);
            $this->taskQueue->purge();
            return ApiResponse::ok('Queue purged');
        } catch (\Throwable $e) {
            $this->logger->error('Queue purge failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function health(Server $server): HealthResponse
    {
        /** @var array{tasking_num: int, task_worker_num: int} $stats */
        $stats = $server->stats();

        $taskLastCreated = (int) $this->cache->get('task-last-created');

        return new HealthResponse(
            status: 'ok',
            phpVersion: PHP_VERSION,
            memoryMb: round(memory_get_usage(false) / 1024 / 1024, 2),
            tasksInProgress: $stats['tasking_num'],
            taskWorkers: $stats['task_worker_num'],
            idleWorkers: $stats['task_worker_num'] - $stats['tasking_num'],
            taskLastCreated: $taskLastCreated,
        );
    }

    /**
     * @throws RateLimitExceededException
     */
    private function rateLimit(string $limiterName, Request $request): void
    {
        /** @var array<string, string> $serverParams */
        $serverParams = $request->server;
        $ip = $serverParams['remote_addr'] ?? '0.0.0.0';
        if (!$this->rateLimiterService->checkLimit($limiterName, $ip)) {
            throw new RateLimitExceededException('Too many requests. Please slow down.');
        }
    }
}
