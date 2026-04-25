<?php

declare(strict_types=1);

namespace App\DTO\Http\Request;

final readonly class CreateTasks
{
    public function __construct(
        public int $count,
        public int $maxConcurrent,
        public bool $stressMode,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var array{count?: int|string, max_concurrent?: int|string, stress_mode?: bool|string} $payload */
        return new self(
            count: (int) ($payload['count'] ?? 1),
            maxConcurrent: (int) ($payload['max_concurrent'] ?? 2),
            stressMode: (bool) ($payload['stress_mode'] ?? false),
        );
    }
}
