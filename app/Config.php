<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

class Config
{
    private array $repository = [];

    public function __construct(string $path)
    {
        $dotenv = Dotenv::createImmutable($path);
        $this->repository = $dotenv->load();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }
}
