<?php

declare(strict_types=1);

namespace App\Service\Provider\App;

use App\Contract\Provider\ServiceProvider;
use App\Contract\Storage\CacheStorage;
use App\Server\Options;
use App\Service\RateLimiter\RateLimiterConfig;
use App\Service\RateLimiter\RateLimiterService;
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
            RateLimiterService::class => static function (ContainerInterface $c): RateLimiterService {
                /** @var CacheStorage $storage */
                $storage = $c->get(CacheStorage::class);

                /** @var LoggerInterface $logger */
                $logger = $c->get(LoggerInterface::class);

                /** @var RateLimiterConfig */
                $config = $c->get(RateLimiterConfig::class);

                return new RateLimiterService($storage, $config, $logger);
            },
        ];
    }
}
