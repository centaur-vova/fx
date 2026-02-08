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
    ) {
    }

    public function toArray(): array
    {
        return [
            'usage' => $this->usage,
            'max' => $this->max,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
