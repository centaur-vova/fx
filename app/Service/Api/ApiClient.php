<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Exception\Api\ApiException;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Http\Client;

class ApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return ?array<string, mixed>
     */
    public function get(string $path, array $query = []): ?array
    {
        /**
         * @var ?array<string, mixed> $result
         */
        $result = $this->request('GET', $path, $query);
        return $result;
    }

    /**
     * @param array<string, mixed> $query
     * @return ?array<string, mixed>
     */
    public function post(string $path, array $query = []): ?array
    {
        /**
         * @var ?array<string, mixed> $result
         */
        $result = $this->request('POST', $path, $query);
        return $result;
    }

    /**
     * @param array<string, mixed> $query
     * @return ?array<string, mixed>
     *
     * @throws ApiException
     */
    private function request(string $method, string $path, array $query = []): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $urlParts = parse_url($url);

        if (!isset($urlParts['host'])) {
            $this->logger->error('Invalid URL', ['url' => $url]);
            return null;
        }

        $ssl = ($urlParts['scheme'] ?? 'http') === 'https';
        $host = $urlParts['host'];
        $port = $urlParts['port'] ?? ($ssl ? 443 : 80);
        $path = $urlParts['path'] ?? '/';

        if ($method === 'GET' && !empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        $client = new Client($host, $port, $ssl);
        $client->setMethod($method);
        $client->setHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        if ($method === 'POST') {
            $client->setData(json_encode($query, JSON_THROW_ON_ERROR));
        }

        $client->execute($path);

        if ($client->errCode !== 0) {
            $this->logger->error('HTTP request failed', [
                'url' => $url,
                'error' => $client->errMsg,
                'statusCode' => $client->statusCode,
            ]);
            $client->close();
            return null;
        }

        $body = $client->body;
        $client->close();
        if (empty($body)) {
            return null;
        }

        if ($client->statusCode !== 200) {
            throw new ApiException('Api error: ' . $body);
        }

        $data = json_decode((string) $body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Failed to decode JSON response', [
                'body' => $body,
                'error' => json_last_error_msg(),
            ]);
            throw new ApiException('Failed to decode JSON response: ' . json_last_error_msg());
        }

        /** @var ?array<string, mixed> $data */
        return $data;
    }
}
