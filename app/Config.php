<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

class Config
{
    public function __construct(
        private readonly array $repository,
    ) {
    }

    public static function fromEnv(string $path): self
    {
        $dotenv = Dotenv::createImmutable($path);

        return new self(array_merge($_ENV, $dotenv->load()));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }
}
