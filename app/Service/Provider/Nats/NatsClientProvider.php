<?php

declare(strict_types=1);

namespace App\Service\Provider\Nats;

use App\Server\Options;
use App\Service\Messaging\Nats\ReconnectableClient;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\Provider\Contract\WorkerStartAware;
use Basis\Nats\Client as NatsClient;
use Basis\Nats\Configuration as NatsConfiguration;

use function DI\autowire;

use DI\ContainerBuilder;

use function DI\get;

use Psr\Container\ContainerInterface;
use Swoole\Server;

class NatsClientProvider implements ServiceProvider, WorkerStartAware
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            // Config
            NatsConfiguration::class => function (ContainerInterface $container): \Basis\Nats\Configuration {
                /** @var Options $options */
                $options = $container->get(Options::class);

                return new NatsConfiguration(
                    host: $options->natsHost,
                    port: $options->natsPort,
                    token: $options->natsToken,
                    timeout: $options->natsTimeoutSec,
                    pingInterval: $options->natsPingIntervalSec,
                );
            },

            // Client
            NatsClient::class => autowire(ReconnectableClient::class)
                ->constructorParameter('configuration', get(NatsConfiguration::class)),
        ];
    }

    /**
     * Avoid NATS disconnect
     *
     * Конь должен жЫть
     */
    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void
    {
        /** @var Options $options */
        $options = $container->get(Options::class);

        /** @var ReconnectableClient $client */
        $client = $container->get(NatsClient::class);
        $client->startPingTimer($options->natsWorkerPingIntervalSec);
    }
}
