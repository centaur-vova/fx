<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\Tasks\QueueFullException;
use App\Services\Tasks\TaskService;
use App\WebSocket\MessageHub;

class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly MessageHub $wsHub,
    ) {
    }

    public function createTasks(int $count, int $delay = 0, int $maxConcurrent = 2): array
    {
        try {
            $taskIds = $this->taskService->createBatch($count, $delay, $maxConcurrent);
            return [
                'success' => true,
                'task_ids' => $taskIds,
                'message' => "{$count} task(s) queued",
            ];

        } catch (QueueFullException $e) {
            return [
                'success' => false,
                'task_ids' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    public function health(): array
    {
        return [
            'status' => 'ok',
            'system' => [
                'php_version' => PHP_VERSION,
                'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'ws_connections' => $this->wsHub->count(),
            ],
            'queue' => $this->taskService->getQueueStats(),
        ];
    }
}
