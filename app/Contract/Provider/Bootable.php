<?php

declare(strict_types=1);

namespace App\Contract\Provider;

use Psr\Container\ContainerInterface;

/**
 * Interface for components that need to be explicitly booted after the container
 * is built, but before the main server starts accepting requests.
 */
interface Bootable
{
    /**
     * Boot after container is built, before server starts
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void;
}
