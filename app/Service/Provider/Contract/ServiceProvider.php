<?php

declare(strict_types=1);

namespace App\Service\Provider\Contract;

use DI\Container;
use DI\ContainerBuilder;

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
