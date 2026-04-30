<?php

declare(strict_types=1);

namespace App\Service\Provider\Api;

use App\Contract\Provider\ServiceProvider;
use App\Server\Options;
use App\Service\Api\ApiClient;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ApiServiceProvider implements ServiceProvider
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            ApiClient::class => $this->registerApiClient(...),
        ];
    }

    private function registerApiClient(ContainerInterface $c): ApiClient
    {
        /** @var Options $options */
        $options = $c->get(Options::class);
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);

        return new ApiClient(
            baseUrl: $options->apiUrl,
            apiToken: $options->apiToken,
            logger: $logger,
        );
    }
}
