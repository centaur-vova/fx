<?php

declare(strict_types=1);

namespace App\Service\Provider\Swoole;

use App\Contract\Storage\KeyValueStorage;
use App\Server\Options;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\Storage\Swoole\SwooleTableKeyValueStorage;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class SwooleTableKeyValueStorageServiceProvider implements ServiceProvider
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            KeyValueStorage::class => function (ContainerInterface $container): \App\Service\Storage\Swoole\SwooleTableKeyValueStorage {
                /** @var Options $options */
                $options = $container->get(Options::class);

                /** @var LoggerInterface $logger */
                $logger = $container->get(LoggerInterface::class);

                $storage = new SwooleTableKeyValueStorage(
                    logger: $logger,
                    size: $options->taskMetaCacheSize ?? 1024,
                    ttl: $options->taskMetaTtlSec,
                );

                // Purge expired items
                $storage->startCleaner(60);

                return $storage;
            },
        ];
    }
}
