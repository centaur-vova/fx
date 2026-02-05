<?php

declare(strict_types=1);

namespace App;

use Exception;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private array $definitions = [];
    private array $instances = [];

    public function set(string $id, callable $definition): void
    {
        $this->definitions[$id] = $definition;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new Exception("Service not found in container: $id");
        }

        return $this->instances[$id] = ($this->definitions[$id])($this);
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || isset($this->instances[$id]);
    }

    public function forget(string $id): void
    {
        unset($this->instances[$id]);
    }
}
