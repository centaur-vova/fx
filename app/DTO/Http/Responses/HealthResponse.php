<?php

declare(strict_types=1);

namespace App\DTO\Http\Responses;

use JsonSerializable;

final readonly class HealthResponse implements JsonSerializable
{
    public function __construct(
        public string $status,
        public string $appVersion,
        public string $phpVersion,
        public float $memoryMb,
        public int $connections,
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
                'connections' => $this->connections,
            ],
        ];
    }
}
