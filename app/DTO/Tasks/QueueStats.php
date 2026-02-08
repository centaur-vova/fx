<?php

declare(strict_types=1);

namespace App\DTO\Tasks;

use App\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class QueueStats implements Arrayable, JsonSerializable
{
    public function __construct(
        public int $usage,
        public int $max,
        public int $tasks,
    ) {
    }

    public function toArray(): array
    {
        return [
            'usage' => $this->usage,
            'max' => $this->max,
            'tasks' => $this->tasks,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
