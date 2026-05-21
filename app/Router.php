<?php

declare(strict_types=1);

namespace App;

use App\Contract\Exception\HttpException;
use App\Controller\TaskController;
use App\DTO\Http\Request\CreateTasks;
use App\DTO\Http\Response\ApiResponse;
use App\Exception\Http\InternalServerErrorException;
use App\Exception\Http\NotFoundException;
use App\Service\Telemetry\TraceContext;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;

class Router
{
    /** @var array<string, array{0: object|string, 1: string}> Map: "METHOD|/path" => [Controller, Method] */
    private array $routes = [];

    public function __construct(
        private readonly TaskController $taskController,
        private readonly LoggerInterface $logger,
    ) {
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->routes = [
            'POST|/api/tasks/create' => [$this->taskController, 'createTasks'],
            'POST|/api/tasks/purge' => [$this->taskController, 'purgeQueue'],
            'GET|/api/tasks/health' => [$this->taskController, 'health'],
        ];
    }

    public function handle(Request $request, Response $response, Server $server): void
    {
        /** @var array<string, string> $serverParams */
        $serverParams = $request->server;

        $path = $serverParams['request_uri'] ?? '/';
        // fast return for websockets
        if ($path === '/ws') {
            return;
        }

        $method = $serverParams['request_method'] ?? 'GET';
        $key = "$method|$path";

        $this->setDefaultHeaders($response);

        if ($method === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return;
        }

        try {
            [$controller, $action] = $this->routes[$key];

            try {
                if (!isset($this->routes[$key])) {
                    throw new NotFoundException('Not Found');
                }

                $result = TraceContext::run(
                    "http.{$method}",
                    null,
                    SpanKind::KIND_SERVER,
                    [
                        'http.method' => $method,
                        'http.target' => $path,
                    ],
                    function (SpanInterface $span) use ($controller, $action, $request, $server, $path) {
                        $payload = $this->getJsonPayload($request);

                        return match ($path) {
                            '/api/tasks/create' => $controller->$action($request, CreateTasks::fromArray($payload)),
                            '/api/tasks/purge' => $controller->$action($request),
                            default => $controller->$action($server),
                        };
                    },
                    [HttpException::class] // Dont treat these exceptions as errors in tracing
                );

            } catch (HttpException $e) {
                $status = $e->getHttpStatus();
                $response->status($status->value);

                $result = ApiResponse::error($e->getMessage());

            } catch (\Throwable $e) {
                $this->logger->error('Unhandled exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                throw new InternalServerErrorException($e->getMessage());
            }

        } finally {
            TraceContext::flush();
        }

        $json = json_encode($result);
        $response->end(is_string($json) ? $json : '{}');
    }

    private function setDefaultHeaders(Response $response): void
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type');
        $response->header('Content-Type', 'application/json');
    }

    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    private function getJsonPayload(Request $request): array
    {
        $raw = $request->getContent();
        if (!$raw) {
            return [];
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);

        /** @var array<string, mixed> $result */
        $result = is_array($data) ? $data : [];

        return $result;
    }
}
