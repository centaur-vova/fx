<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\Storage\CacheStorage;
use App\Contract\Task\TaskQueue;
use App\DTO\Http\Request\CreateTasks;
use App\DTO\Http\Response\ApiResponse;
use App\DTO\Http\Response\HealthResponse;
use App\Exception\Http\BadRequestException;
use App\Exception\Http\InternalServerErrorException;
use App\Exception\Http\InvalidTaskBatchException;
use App\Exception\Http\RateLimitExceededException;
use App\Service\Api\BalancerApi;
use App\Service\RateLimiter\RateLimiterService;
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
        private readonly BalancerApi $balancerApi,
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
        if ($dto->inRandomMode()) {
            go(fn () => $this->taskService->createRandomBatches());
            return ApiResponse::ok('RAND mode initiated');
        }

        // Guess mode
        go(function () use ($dto): void {
            assert($dto->semaphoreDriver !== null);
            assert($dto->taskMode !== null);

            $this->taskService->createBatch($dto->count, $dto->maxConcurrent, $dto->semaphoreDriver, $dto->taskMode);
        });

        return ApiResponse::ok('Tasks queued');
    }

    /**
     * @param array<string, mixed> $payload
     * @throws RateLimitExceededException
     */
    public function purgeQueue(Server $server, Request $request, array $payload): ApiResponse
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

    /**
     * @param array<string, mixed> $payload
     *
     * TODO: move to the separate controller
     * TODO: add caching
     */
    public function health(Server $server, Request $request, array $payload): HealthResponse
    {
        // Balancer's health
        $balancerHealth = $this->balancerApi->health();

        // PHP Swoole specific data
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
            balancerHealth: $balancerHealth,
        );
    }

    /**
     * Mark a specific instance as alive/unalived
     *
     * @param array<string, mixed> $payload
     */
    public function toggleInstance(Server $server, Request $request, array $payload): ApiResponse
    {
        $this->rateLimit('toggle-instance', $request);

        $hash = $payload['hash'] ?? null;
        $alive = $payload['alive'] ?? false;

        if (!$hash || !is_string($hash)) {
            throw new BadRequestException('Instance hash required');
        }

        try {
            if ($alive) {
                if ($this->balancerApi->reviveInstance($hash)) {
                    return ApiResponse::ok('API Instance successfully revived');
                }
            } else {
                if ($this->balancerApi->forceUnaliveInstance($hash)) {
                    return ApiResponse::ok('API Instance successfully unalived');
                }
            }

            // Shouldn't get here
            throw new InternalServerErrorException();
        } catch (\Throwable $e) {
            $this->logger->error('Kill instance failed', ['hash' => $hash, 'error' => $e->getMessage()]);
            throw new InternalServerErrorException('Internal error');
        }
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
