<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTO\Http\Responses\ApiResponse;
use App\DTO\Http\Responses\HealthResponse;
use App\Exceptions\Tasks\QueueFullException;
use App\Services\Tasks\TaskService;
use App\WebSocket\MessageHub;

class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly MessageHub $wsHub,
        private readonly string $appVersion,
    ) {
    }

    public function createTasks(int $count, int $delay = 0, int $maxConcurrent = 2): ApiResponse
    {
        try {
            $this->taskService->createBatch($count, $delay, $maxConcurrent);
            return ApiResponse::ok("{$count} task(s) queued");
        } catch (QueueFullException $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    public function health(): HealthResponse
    {
        return new HealthResponse(
            appVersion: $this->appVersion,
            status: 'ok',
            phpVersion: PHP_VERSION,
            memoryMb: round(memory_get_usage(false) / 1024 / 1024, 2),
            connections: $this->wsHub->count(),
        );
    }
}
