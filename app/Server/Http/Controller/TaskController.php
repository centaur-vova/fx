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

final readonly class TaskController
{
    public function __construct(
        private TaskService $taskService,
        private TaskQueue $taskQueue,
        private CacheStorage $cache,
        private int $taskMaxBatchSize,
        private int $taskSemaphoreLimit,
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

        // Run tasks creation in coroutine
        go(fn () => $this->dispatchBatch($dto));

        return ApiResponse::ok('Tasks queued');
    }

    #[Route(method: 'POST', path: '/tasks/ford-bronco')]
    #[RateLimit(limiterName: 'create-tasks')]
    public function fordBronco(): ApiResponse
    {
        // Save timestamp when last createTasks request was sent
        $this->cache->set('task-last-created', (string) time(), 30 * 60); // Keep for 30 minutes

        go(fn () => $this->taskService->createRandomBatches());

        return ApiResponse::ok('🐎 Ford Bronco unleashed — hold your horses!');
    }

    #[Route(method: 'POST', path: '/tasks/purge')]
    #[RateLimit(limiterName: 'purge-queue')]
    public function purgeQueue(): ApiResponse
    {
        $this->taskQueue->purge();
        return ApiResponse::ok('Queue purged');
    }

    private function dispatchBatch(CreateTasks $dto): void
    {
        $this->taskService->createBatch(
            $dto->count,
            $dto->maxConcurrent,
            $dto->semaphoreDriver,
            $dto->taskMode
        );
    }
}
