<?php

declare(strict_types=1);

namespace App\Contract\Provider;

use Psr\Container\ContainerInterface;

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
