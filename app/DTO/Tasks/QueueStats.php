<?php

declare(strict_types=1);

namespace App\DTO\Tasks;

use JsonSerializable;

class QueueStats implements JsonSerializable
{
    public function __construct(
        public readonly int $usage,
        public readonly int $max,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'usage' => $this->usage,
            'max' => $this->max,
        ];
    }
}
