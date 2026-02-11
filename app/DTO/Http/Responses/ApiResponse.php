<?php

namespace App\DTO\Http\Responses;

use JsonSerializable;

final readonly class ApiResponse implements JsonSerializable
{
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
    ) {
    }

    public static function ok(string $message, array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }

    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data'    => $this->data,
        ];
    }
}
