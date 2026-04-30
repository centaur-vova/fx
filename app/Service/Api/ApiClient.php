<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Exception\Api\ApiException;
use Psr\Log\LoggerInterface;
use Swoole\ConnectionPool;
use Swoole\Coroutine\Http\Client;

class ApiClient
{
    public function __construct(
        private readonly ConnectionPool $pool,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return ?array<string, mixed>
     */
    public function get(string $path, array $data = []): ?array
    {
        /**
         * @var ?array<string, mixed> $result
         */
        $result = $this->request('GET', $path, $data);
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return ?array<string, mixed>
     */
    public function post(string $path, array $data = []): ?array
    {
        /**
         * @var ?array<string, mixed> $result
         */
        $result = $this->request('POST', $path, $data);
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     *
     * @throws ApiException
     * @throws \JsonException
     */
    private function request(string $method, string $path, array $data = []): ?array
    {
        // Get a pre-configured persistent client from the pool
        /** @var Client|false $client */
        $client = $this->pool->get();
        if (!$client) {
            $this->logger->error('Failed to get HTTP client from pool');
            return null;
        }

        try {
            $client->setMethod($method);

            // Prepare request path and query string
            $requestPath = '/' . ltrim($path, '/');
            if ($method === 'GET' && !empty($data)) {
                $requestPath .= '?' . http_build_query($data);
            }

            if ($method === 'POST') {
                $client->setData(json_encode($data, JSON_THROW_ON_ERROR));
            }

            // Execute request using keep-alive connection
            $client->execute($requestPath);

            // Check for low-level socket or timeout errors
            if ($client->errCode !== 0) {
                // Fetching properties as local variables helps PHPStan understand they are not mixed
                $errCode = (int) $client->errCode;
                $errMsg = (string) $client->errMsg;

                $this->logger->error('HTTP request failed', [
                    'path' => $requestPath,
                    'error' => $errMsg,
                    'code' => $errCode,
                ]);

                $client = null;
                return null;
            }

            /** @var string|null $body */
            $body = $client->body;
            if (empty($body)) {
                return null;
            }

            /** @var int $statusCode */
            $statusCode = $client->statusCode;
            if ($statusCode !== 200) {
                throw new ApiException('Api error: ' . $body);
            }

            /** @var array<string, mixed> $responseData */
            $responseData = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);

            /** @var ?array<string, mixed> $responseData */
            return $responseData;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to decode JSON response', ['error' => $e->getMessage()]);
            throw new ApiException('Failed to decode JSON response: ' . $e->getMessage());
        } finally {
            // Crucial: Always return the client to the pool
            $this->pool->put($client);
        }
    }
}
