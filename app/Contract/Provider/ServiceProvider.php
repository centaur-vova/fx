<?php

declare(strict_types=1);

namespace App\Contract\Provider;

use DI\Container;
use DI\ContainerBuilder;

/**
 * Standard service provider interface for registering dependencies into the
 * PHP-DI container.
 */
interface ServiceProvider
{
    /**
     * Register services into the container
     *
     * @param ContainerBuilder<Container> $builder
     * @return array<string, mixed> PHP-DI definitions
     */
    public function register(ContainerBuilder $builder): array;
}
