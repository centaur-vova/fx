<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;

class ConfigLoader
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

    public function getString(string $key, string $default = ''): string
    {
        $val = $this->raw($key, $default);
        return is_scalar($val) ? (string) $val : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->raw($key, $default);
        return is_scalar($val) ? (int) $val : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->raw($key, $default);
        return is_scalar($val) ? (float) $val : $default;
    }

    private function raw(string $key, mixed $default = null): mixed
    {
        return $this->repository[$key] ?? $default;
    }
}
