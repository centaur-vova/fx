<?php

declare(strict_types=1);

namespace App\DTO\Http\Requests;

final readonly class CreateTasks
{
    public function __construct(
        public int $count,
        public int $delay,
        public int $maxConcurrent,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var array{count?: int|string, delay?: int|string, max_concurrent?: int|string} $payload */
        return new self(
            count: (int) ($payload['count'] ?? 1),
            delay: (int) ($payload['delay'] ?? 0),
            maxConcurrent: (int) ($payload['max_concurrent'] ?? 2),
        );
    }
}
