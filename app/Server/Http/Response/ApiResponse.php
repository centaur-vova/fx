<?php

declare(strict_types=1);

namespace App\Server\Http\Response;

use App\Contract\Exception\HttpException;
use App\Contract\Http\HttpStatus;
use JsonSerializable;
use Throwable;

final readonly class ApiResponse implements JsonSerializable
{
    private function __construct(
        public bool $success,
        public string $message,
        public HttpStatus $status = HttpStatus::OK,
        public ?JsonSerializable $data = null,
    ) {
    }

    public static function ok(string $message, ?JsonSerializable $data = null): self
    {
        return new self(true, $message, HttpStatus::OK, $data);
    }

    public static function error(string $message, HttpStatus $status, ?JsonSerializable $data = null): self
    {
        return new self(false, $message, $status, $data);
    }

    public static function fromException(Throwable $e): self
    {
        $status = match(true) {
            $e instanceof HttpException => $e->getHttpStatus(),
            default => HttpStatus::INTERNAL_SERVER_ERROR,
        };

        return new self(false, $e->getMessage(), $status);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $response = [
            'success' => $this->success,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $response['data'] = $this->data->jsonSerialize();
        }

        return $response;
    }
}
