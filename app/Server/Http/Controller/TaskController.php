<?php

declare(strict_types=1);

namespace App\Server\Http\Controller;

use App\Contract\Storage\CacheStorage;
use App\Contract\Task\SemaphoreDriver;
use App\Contract\Task\TaskMode;
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
        private int $maxBatchSize,
        private int $semaphoreLimit,
    ) {
    }

    #[Route(method: 'POST', path: '/tasks/create')]
    #[RateLimit(limiterName: 'create-tasks')]
    public function createTasks(CreateTasks $dto): ApiResponse
    {
        // Save timestamp when last createTasks request was sent
        $this->cache->set('task-last-created', (string) time(), 30 * 60); // Keep for 30 minutes

        // Validate DTO
        $dto->validate($this->maxBatchSize, $this->semaphoreLimit);

        // Run tasks creation in coroutine
        go(fn () => $this->dispatchBatch($dto));

        return ApiResponse::ok('Tasks queued');
    }

    #[Route(method: 'POST', path: '/tasks/rand')]
    #[RateLimit(limiterName: 'create-tasks')]
    public function rand(): ApiResponse
    {
        // Save timestamp when last createTasks request was sent
        $this->cache->set('task-last-created', (string) time(), 30 * 60); // Keep for 30 minutes

        go(fn () => $this->taskService->createRandomBatches());

        return ApiResponse::ok('🐎 Ford Bronco unleashed — hold your horses!');
    }

    #[Route(method: 'POST', path: '/tasks/nitro')]
    #[RateLimit(limiterName: 'create-tasks')]
    public function nitro(): ApiResponse
    {
        // Save timestamp when last createTasks request was sent
        $this->cache->set('task-last-created', (string) time(), 30 * 60); // Keep for 30 minutes

        go(fn () => $this->taskService->createBatch(
            count: $this->maxBatchSize,
            maxConcurrent: $this->semaphoreLimit,
            semaphoreDriver: SemaphoreDriver::API,
            mode: TaskMode::OBSERVATION,
        ));

        return ApiResponse::ok('🐎💨 NITRO INJECTED — HOLD YOUR HORSES!');
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
