<?php

declare(strict_types=1);

namespace App\Server\Http\Controller;

use App\Contract\Storage\CacheStorage;
use App\Contract\Task\TaskQueue;
use App\Server\Http\Attribute\RateLimit;
use App\Server\Http\Attribute\Route;
use App\Server\Http\Request\CreateTasks;
use App\Server\Http\Response\ApiResponse;
use App\Service\Task\TaskService;

class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskQueue $taskQueue,
        private readonly CacheStorage $cache,
        private readonly int $taskMaxBatchSize,
        private readonly int $taskSemaphoreLimit,
    ) {
    }

    #[Route(method: 'POST', path: '/tasks/create')]
    #[RateLimit(limiterName: 'create-tasks')]
    public function createTasks(CreateTasks $dto): ApiResponse
    {
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

    #[Route(method: 'POST', path: '/tasks/purge')]
    #[RateLimit(limiterName: 'purge-queue')]
    public function purgeQueue(): ApiResponse
    {
        $this->taskQueue->purge();
        return ApiResponse::ok('Queue purged');
    }
}
