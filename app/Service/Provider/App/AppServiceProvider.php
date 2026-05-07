<?php

declare(strict_types=1);

namespace App\Service\Provider\App;

use App\Contract\Provider\Bootable;
use App\Contract\Provider\ServiceProvider;
use App\Contract\Provider\WorkerStartAware;
use App\Contract\Storage\CacheStorage;
use App\Server\Options;
use App\Server\RuntimeScheduler;
use App\Service\Storage\Swoole\SwooleTableKeyValueStorage;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

class AppServiceProvider implements ServiceProvider, Bootable, WorkerStartAware
{
    private const string CACHE_DRIVER_SWOOLE_TABLE = 'swoole_table';

    public function register(ContainerBuilder $builder): array
    {
        return [
            CacheStorage::class => $this->registerCacheStorage(...),
        ];
    }

    public function onWorkerStart(ContainerInterface $c, Server $server, int $workerId): void
    {
        if ($workerId === 0) {
            /** @var CacheStorage $storage */
            $storage = $c->get(CacheStorage::class);
            /** @var Options $options */
            $options = $c->get(Options::class);
            /** @var RuntimeScheduler $scheduler */
            $scheduler = $c->get(RuntimeScheduler::class);

            $scheduler->tick($storage->cleanExpired(...), $options->cacheAutoCleanSec);
        }
    }

    public function boot(ContainerInterface $container): void
    {
        // Force CacheStorage initialization in master process before worker fork
        $container->get(CacheStorage::class);
    }

    private function registerCacheStorage(ContainerInterface $c): CacheStorage
    {
        /** @var Options Options */
        $options = $c->get(Options::class);
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);

        // Storage driver
        $driver = $options->cacheStorageDriver;

        $storage = match ($driver) {
            self::CACHE_DRIVER_SWOOLE_TABLE => new SwooleTableKeyValueStorage(
                logger: $logger,
                size: $options->cacheMaxSize,
                ttl: $options->cacheDefaultTtlSec,
                maxValueSize: $options->cacheValueMaxSize,
            ),
            default => throw new \RuntimeException("Unsupported storage driver: {$driver}"),
        };

        return $storage;
    }
}
