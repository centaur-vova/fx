<?php

declare(strict_types=1);

namespace App\Service\Provider\Api;

use App\Contract\Provider\ServiceProvider;
use App\Contract\Provider\WorkerStartAware;
use App\Server\Options;
use App\Service\Api\ApiClient;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\ConnectionPool;
use Swoole\Coroutine\Http\Client;
use Swoole\Server;

/**
 * API service provider for registering HTTP client and connection pool.
 *
 * Provides a Swoole connection pool for the ApiClient with pre-configured
 * timeout, TLS, and authentication headers. Implements worker-start awareness
 * for pool warmup.
 */
final readonly class ApiServiceProvider implements ServiceProvider, WorkerStartAware
{
    private const int CONNECTION_POOL_SIZE = 1024;

    /**
     * Registers services into the DI container.
     *
     * @return array<string, callable> Service definitions
     */
    public function register(ContainerBuilder $builder): array
    {
        return [
            ConnectionPool::class => function (ContainerInterface $c): ConnectionPool {
                /** @var Options $options */
                $options = $c->get(Options::class);

                // Parse URL parts
                $urlParts = parse_url($options->apiUrl);

                // Short & sweet: telling PHPStan to trust us
                assert(is_array($urlParts) && isset($urlParts['host']));

                $host = $urlParts['host'];
                $ssl = ($urlParts['scheme'] ?? 'http') === 'https';
                $port = (int)($urlParts['port'] ?? ($ssl ? 443 : 80));

                // Add a 30% safety margin to the connection timeout to ensure
                // the PHP worker doesn't drop the connection before the Go semaphore expires.
                $timeout = (int) ($options->taskLockTimeoutSec * 1.3);

                // API Token
                $apiAuthKey = $options->apiAuthKey;

                return new ConnectionPool(function () use ($host, $port, $ssl, $apiAuthKey, $timeout): Client {
                    $client = new Client($host, $port, $ssl);
                    $client->set([
                        'timeout' => $timeout,
                        'keep_alive' => true,
                    ]);
                    $client->setHeaders([
                        'Authorization' => 'Bearer ' . $apiAuthKey,
                        'Accept' => 'application/json',
                    ]);
                    return $client;
                }, self::CONNECTION_POOL_SIZE);
            },
            ApiClient::class => $this->registerApiClient(...),
        ];
    }

    /**
     * Registers the ApiClient service.
     *
     * @param ContainerInterface $c The DI container
     *
     * @return ApiClient The configured API client
     */
    private function registerApiClient(ContainerInterface $c): ApiClient
    {
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);
        /** @var ConnectionPool $pool */
        $pool = $c->get(ConnectionPool::class);

        return new ApiClient(
            pool: $pool,
            logger: $logger,
        );
    }

    /**
     * Called when a worker process starts.
     *
     * Warms up the connection pool to avoid latency on the first real request.
     * Currently only worker #0 is used for future JWT token prefetch.
     *
     * @param ContainerInterface $container The DI container
     * @param Server             $server   The Swoole server instance
     * @param int                $workerId The ID of the started worker
     */
    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void
    {
        // Warmup connection pool in the worker process
        /** @var ConnectionPool $pool */
        $pool = $container->get(ConnectionPool::class);

        // Force initialize at least one connection to avoid latency on the first real request.
        $client = $pool->get();

        // Return the client to the pool. We pass null if we want the pool to manage
        // the object lifecycle or if the initial connection failed.
        $pool->put($client);

        // Only in worker #0 - try fetching the JWT token
        if ($workerId === 0) {
            // TODO: [AUTH] Pre-fetch JWT token on worker startup to avoid
            //       first-request latency. Should be done asynchronously
            //       or with a fallback to the first API call.
        }
    }
}
