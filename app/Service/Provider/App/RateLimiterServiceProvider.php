<?php

declare(strict_types=1);

namespace App\Service\Provider\App;

use App\Contract\Storage\RateLimiterStorage;
use App\Contract\Storage\TtlKeyValueStorage;
use App\Server\Options;
use App\Service\Provider\Contract\ServiceProvider;
use App\Service\RateLimiter\RateLimiterConfig;
use App\Service\RateLimiter\RateLimiterService;
use App\Service\Storage\Swoole\SwooleTableKeyValueStorage;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class RateLimiterServiceProvider implements ServiceProvider
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            RateLimiterConfig::class => function (ContainerInterface $c): RateLimiterConfig {
                /** @var Options $options */
                $options = $c->get(Options::class);
                return new RateLimiterConfig($options->rateLimiters);
            },
            RateLimiterStorage::class => static function (ContainerInterface $c): TtlKeyValueStorage {
                /** @var Options $options */
                $options = $c->get(Options::class);
                /** @var LoggerInterface $logger */
                $logger = $c->get(LoggerInterface::class);

                return new SwooleTableKeyValueStorage(
                    logger: $logger,
                    size: $options->rateLimiterTableSize,
                    ttl: $options->rateLimiterTtl,
                );
            },
            RateLimiterService::class => static function (ContainerInterface $c): RateLimiterService {
                /** @var RateLimiterStorage $storage */
                $storage = $c->get(RateLimiterStorage::class);

                /** @var Options $options */
                $options = $c->get(Options::class);

                /** @var LoggerInterface $logger */
                $logger = $c->get(LoggerInterface::class);

                /** @var RateLimiterConfig */
                $config = $c->get(RateLimiterConfig::class);

                $cleanInterval = $options->rateLimiterCleanupInterval;

                // Clean expired keys
                $storage->startCleaner($cleanInterval);

                return new RateLimiterService($storage, $config, $logger);
            },
        ];
    }
}
