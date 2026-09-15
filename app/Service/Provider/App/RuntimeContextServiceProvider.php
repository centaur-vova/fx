<?php

declare(strict_types=1);

namespace App\Service\Provider\App;

use App\Contract\Provider\Bootable;
use App\Contract\Provider\ServiceProvider;
use App\Server\RuntimeContext;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

use function DI\autowire;

class RuntimeContextServiceProvider implements ServiceProvider, Bootable
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            RuntimeContext::class => autowire(),
        ];
    }

    public function boot(ContainerInterface $c): void
    {
        // Warmup in the master process
        $c->get(RuntimeContext::class);
    }
}
