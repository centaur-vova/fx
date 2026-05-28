<?php

declare(strict_types=1);

namespace App\Service\Provider\Cache;

use App\Contract\Provider\Bootable;
use App\Contract\Provider\ServiceProvider;
use App\Contract\Provider\WorkerStartAware;
use App\Contract\Storage\ActiveEvictionStorage;
use App\Contract\Storage\CacheStorage;
use App\Server\Options;
use App\Server\RuntimeScheduler;
use App\Service\Storage\Swoole\SwooleTableKeyValueStorage;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

class SwooleTableCacheProvider implements ServiceProvider, Bootable, WorkerStartAware
{
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

            if ($storage instanceof ActiveEvictionStorage) {
                $scheduler->tick($storage->cleanExpired(...), $options->cacheAutoCleanSec);
            }
        }
    }

    public function boot(ContainerInterface $container): void
    {
        // Force CacheStorage initialization in master process before worker fork
        $container->get(CacheStorage::class);
    }

    private function registerCacheStorage(ContainerInterface $c): CacheStorage
    {
        /** @var Options $options */
        $options = $c->get(Options::class);
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);

        $storage = new SwooleTableKeyValueStorage(
            logger: $logger,
            size: $options->cacheMaxSize,
            ttl: $options->cacheDefaultTtlSec,
            maxValueSize: $options->cacheValueMaxSize,
        );

        return $storage;
    }
}
