<?php

declare(strict_types=1);

namespace App\DTO\Http\Responses;

use JsonSerializable;

final readonly class ApiResponse implements JsonSerializable
{
    /**
     * @param bool $success
     * @param string $message
     * @param array<string, mixed> $data
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
    ) {
    }

    /**
     * @param string $message
     * @param array<string, mixed> $data
     */
    public static function ok(string $message, array $data = []): self
    {
        return new self(true, $message, $data);
    }

    /**
     * @param string $message
     */
    public static function error(string $message): self
    {
        return new self(false, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
