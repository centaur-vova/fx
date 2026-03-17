<?php

declare(strict_types=1);

namespace App\Service\Provider\Nats;

use App\Contract\Messaging\Broadcaster;
use App\Service\Messaging\Nats\NatsBroadcaster;
use App\Service\Provider\Contract\ServiceProvider;

use function DI\autowire;

use DI\ContainerBuilder;

use function DI\get;

class NatsBroadcasterServiceProvider implements ServiceProvider
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
