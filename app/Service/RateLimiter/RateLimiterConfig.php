<?php

declare(strict_types=1);

namespace App\Service\RateLimiter;

class RateLimiterConfig
{
    /**
     * @var array<string, array{max_attempts: int, ttl: int}>
     */
    private array $configs;

    /**
     * @param array<string, array{max_attempts?: int, ttl?: int}> $configs
     */
    public function __construct(array $configs)
    {
        foreach ($configs as $name => $config) {
            if (!isset($config['max_attempts']) || !isset($config['ttl'])) {
                throw new \InvalidArgumentException("Missing max_attempts or ttl for rate limiter '$name'");
            }
            if ($config['max_attempts'] <= 0 || $config['ttl'] <= 0) {
                throw new \InvalidArgumentException("Missing max_attempts or ttl for rate limiter '$name'");
            }
            $this->configs[$name] = [
                'max_attempts' => (int) $config['max_attempts'],
                'ttl' => (int) $config['ttl'],
            ];
        }
    }

    public function getMaxAttempts(string $limiterName): int
    {
        return $this->configs[$limiterName]['max_attempts'] ?? 0;
    }

    public function getTtl(string $limiterName): int
    {
        return $this->configs[$limiterName]['ttl'] ?? 0;
    }

    public function has(string $limiterName): bool
    {
        return isset($this->configs[$limiterName]);
    }
}
