<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Http\Request\CreateTasks;
use App\DTO\Http\Response\ApiResponse;
use App\DTO\Http\Response\HealthResponse;
use App\Exception\Task\InvalidTaskBatchException;
use App\Exception\Task\QueueFullException;
use App\Service\Task\Processor\ProcessorFactory;
use App\Service\Task\TaskService;
use App\WebSocket\MessageHub;

class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly MessageHub $wsHub,
        private readonly string $appVersion,
        private readonly int $stressMinTaskNum,
        private readonly int $taskMaxBatchSize,
        private readonly int $taskSemaphoreLimit,
    ) {
    }

    public function createTasks(CreateTasks $dto): ApiResponse
    {
        try {
            // Validate DTO
            if ($dto->count < 1 || $dto->count > $this->taskMaxBatchSize) {
                throw new InvalidTaskBatchException("Count must be between 1 and {$this->taskMaxBatchSize}");
            }

            if ($dto->maxConcurrent < 1 || $dto->maxConcurrent > $this->taskSemaphoreLimit) {
                throw new InvalidTaskBatchException("Concurrency must be between 1 and {$this->taskSemaphoreLimit}");
            }

            // Guess mode
            $mode = $dto->count < $this->stressMinTaskNum
                ? ProcessorFactory::MODE_OBSERVATION
                : ProcessorFactory::MODE_STRESS;

            $this->taskService->createBatch($dto->count, $dto->maxConcurrent, $mode);
            return ApiResponse::ok("{$dto->count} task(s) queued");
        } catch (InvalidTaskBatchException | QueueFullException $e) {
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
