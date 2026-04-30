<?php

declare(strict_types=1);

namespace App\Service\Provider\Nats;

use App\Contract\Messaging\Broadcaster;
use App\Contract\Provider\ServiceProvider;
use App\Service\Messaging\Nats\NatsBroadcaster;

use function DI\autowire;

use DI\ContainerBuilder;

use function DI\get;

final readonly class NatsBroadcasterServiceProvider implements ServiceProvider
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            // Autowiring, yahoooo!
            NatsBroadcaster::class => autowire(),
            Broadcaster::class => get(NatsBroadcaster::class),
        ];
    }
}
