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
use Swoole\Server;

class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
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
            go(function () use ($dto): void {
                $mode = $dto->count < $this->stressMinTaskNum
                ? ProcessorFactory::MODE_OBSERVATION
                : ProcessorFactory::MODE_STRESS;

                $this->taskService->createBatch($dto->count, $dto->maxConcurrent, $mode);
            });

            return ApiResponse::ok('Tasks queued');
        } catch (InvalidTaskBatchException | QueueFullException $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    public function health(Server $server): HealthResponse
    {
        /** @var array{tasking_num: int, task_worker_num: int} $stats */
        $stats = $server->stats();

        return new HealthResponse(
            status: 'ok',
            phpVersion: PHP_VERSION,
            memoryMb: round(memory_get_usage(false) / 1024 / 1024, 2),
            tasksInProgress: $stats['tasking_num'],
            taskWorkers: $stats['task_worker_num'],
            idleWorkers: $stats['task_worker_num'] - $stats['tasking_num'],
        );
    }
}
