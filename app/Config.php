<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

class Config
{
    /**
     * @param array<string, mixed> $repository
     */
    public function __construct(
        private readonly array $repository,
    ) {
    }

    /**
     * Initialize config from system environment and optional .env file
     */
    public static function fromEnv(string $path): self
    {
        $fileData = [];
        $envPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

        // Load from file only if it exists (local dev without Docker)
        if (file_exists($envPath) && is_readable($envPath)) {
            $dotenv = Dotenv::createImmutable($path);
            $fileData = $dotenv->load();
        }

        // Merge: System Env variables have higher priority than .env file
        // This ensures Docker Compose 'env_file' always wins
        return new self(array_merge($fileData, $_ENV));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->get($key, $default));
    }
}
