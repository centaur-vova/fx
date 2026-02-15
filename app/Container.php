<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\Container\ServiceNotFoundException;
use Psr\Container\ContainerInterface;
use TypeError;

class Container implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $definition): void
    {
        $this->definitions[$id] = $definition;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): mixed
    {
        $instance = $this->instances[$id] ?? null;

        if ($instance === null) {
            $definition = $this->definitions[$id] ?? throw new ServiceNotFoundException("Service not found: $id");
            $instance = $this->instances[$id] = $definition($this);
        }

        // PHPStan L9
        if (class_exists($id) || interface_exists($id)) {
            if (!$instance instanceof $id) {
                throw new TypeError("Container error: [$id] is not an instance of expected type");
            }
            return $instance;
        }

        return $instance;
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
