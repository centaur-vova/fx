<?php

declare(strict_types=1);

namespace App\DTO\WebSocket\Concern;

trait InteractsWithWebSocket
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        // @phpstan-ignore-next-line
        return get_object_vars($this);
    }
}
