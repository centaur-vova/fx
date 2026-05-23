<?php

declare(strict_types=1);

namespace App\DTO\Balancer;

use App\Contract\Support\FromArray;
use JsonSerializable;

final readonly class ApiInstanceHealth implements FromArray, JsonSerializable
{
    public function __construct(
        public string $hash,
        public bool $alive,
        public string $cbState,
        public int $requests,
        public int $errors,
    ) {
    }

    /**
     * @param array{hash?: string, alive?: bool, cb_state?: string, requests?: int, errors?: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hash: (string) ($data['hash'] ?? ''),
            alive: (bool) ($data['alive'] ?? false),
            cbState: (string) ($data['cb_state'] ?? 'closed'),
            requests: (int) ($data['requests'] ?? 0),
            errors: (int) ($data['errors'] ?? 0),
        );
    }

    /**
     * @return array{hash: string, alive: bool, cb_state: string, requests: int, errors: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'hash' => $this->hash,
            'alive' => $this->alive,
            'cb_state' => $this->cbState,
            'requests' => $this->requests,
            'errors' => $this->errors,
        ];
    }
}
