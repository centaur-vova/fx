<?php

declare(strict_types=1);

namespace App\Server\Http\Request;

final readonly class ToggleInstance
{
    public function __construct(
        public string $hash,
        public bool $alive,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $hash = is_string($payload['hash'] ?? null)
            ? $payload['hash']
            : '';
        $alive = filter_var($payload['alive'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self($hash, $alive);
    }
}
