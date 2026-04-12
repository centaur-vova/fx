<?php

declare(strict_types=1);

namespace App\DTO\Http\Response;

use JsonSerializable;

final readonly class HealthResponse implements JsonSerializable
{
    public function __construct(
        public string $status,
        public string $appVersion,
        public string $phpVersion,
        public float $memoryMb,
        public int $tasksInProgress,
        public int $taskWorkers,
        public int $idleWorkers,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'system' => [
                'php_version' => $this->phpVersion,
                'app_version' => $this->appVersion,
                'memory_mb' => $this->memoryMb,
            ],
            'queue' => [
                'tasks_in_progress' => $this->tasksInProgress,
                'task_workers' => $this->taskWorkers,
                'idle_workers' => $this->idleWorkers,
            ],
        ];
    }
}
