<?php

declare(strict_types=1);

namespace App\Service\Provider\Nats;

use App\Contract\Messaging\MessageSerializer;
use App\Contract\Queue\Queue;
use App\Server\Options;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\Queue\Nats\NatsQueue;
use Basis\Nats\Client as NatsClient;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

class NatsQueueServiceProvider implements ServiceProvider
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            Queue::class => function (ContainerInterface $c): \App\Service\Queue\Nats\NatsQueue {
                /** @var NatsClient $client */
                $client = $c->get(NatsClient::class);
                /** @var Options $options */
                $options = $c->get(Options::class);
                /** @var MessageSerializer */
                $serializer = $c->get(MessageSerializer::class);

                return new NatsQueue(
                    client: $client,
                    serializer: $serializer,
                    streamName: $options->taskQueueStream,
                );
            },
        ];
    }
}
