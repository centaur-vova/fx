<?php

declare(strict_types=1);

namespace App\DTO\Balancer;

use App\Contract\Support\FromArray;
use JsonSerializable;

final readonly class Health implements FromArray, JsonSerializable
{
    /**
     * @param ApiInstanceHealth[] $instances
     */
    public function __construct(
        public int $up,
        public int $down,
        public int $totalRequests,
        public int $totalErrors,
        public int $uptimeSeconds,
        public array $instances,
    ) {
    }

    /**
     * @param array{
     *     up?: int,
     *     down?: int,
     *     total_requests?: int,
     *     total_errors?: int,
     *     uptime_seconds?: int,
     *     instances?: array<array{hash: string, alive: bool, cb_state: string, requests: int, errors: int}>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $instances = array_map(
            ApiInstanceHealth::fromArray(...),
            $data['instances'] ?? []
        );

        return new self(
            up: (int) ($data['up'] ?? 0),
            down: (int) ($data['down'] ?? 0),
            totalRequests: (int) ($data['total_requests'] ?? 0),
            totalErrors: (int) ($data['total_errors'] ?? 0),
            uptimeSeconds: (int) ($data['uptime_seconds'] ?? 0),
            instances: $instances,
        );
    }

    /**
     * @return array{up: int, down: int, total_requests: int, total_errors: int, uptime_seconds: int, instances: ApiInstanceHealth[]}
     */
    public function jsonSerialize(): array
    {
        return [
            'up' => $this->up,
            'down' => $this->down,
            'total_requests' => $this->totalRequests,
            'total_errors' => $this->totalErrors,
            'uptime_seconds' => $this->uptimeSeconds,
            'instances' => $this->instances,
        ];
    }
}
