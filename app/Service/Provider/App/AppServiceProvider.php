<?php

declare(strict_types=1);

namespace App\Service\Provider\App;

use App\Domain\Cache\Contract\CacheStorage;
use App\Server\Options;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\Provider\Contract\WorkerStartAware;
use App\Service\Storage\Swoole\SwooleTableKeyValueStorage;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

class AppServiceProvider implements ServiceProvider, WorkerStartAware
{
    private const string CACHE_DRIVER_SWOOLE_TABLE = 'swoole_table';

    public function register(ContainerBuilder $builder): array
    {
        return [
            CacheStorage::class => $this->registerCacheStorage(...),
        ];
    }

    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void
    {
        if ($workerId === 0) {
            /** @var CacheStorage $storage */
            $storage = $container->get(CacheStorage::class);

            /** @var Options $options */
            $options = $container->get(Options::class);

            $storage->startCleaner($options->cacheAutoCleanSec);
        }
    }

    private function registerCacheStorage(ContainerInterface $c): CacheStorage
    {
        /** @var Options Options */
        $options = $c->get(Options::class);
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);

        $driver = $options->cacheStorageDriver;

        $storage = match ($driver) {
            self::CACHE_DRIVER_SWOOLE_TABLE => new SwooleTableKeyValueStorage(
                logger: $logger,
                size: $options->cacheMaxSize,
                ttl: $options->cacheDefaultTtlSec,
            ),
            default => throw new \RuntimeException("Unsupported storage driver: {$driver}"),
        };

        return $storage;
    }
}
