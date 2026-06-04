<?php

declare(strict_types=1);

namespace App\Server\Http;

use App\Contract\Exception\HttpException;
use App\Exception\Http\InternalServerErrorException;
use App\Exception\Http\RateLimitExceededException;
use App\Server\Http\Attribute\RateLimit;
use App\Server\Http\Attribute\Route;
use App\Server\Http\Controller\ApiController;
use App\Server\Http\Controller\BenchmarkController;
use App\Server\Http\Controller\HealthController;
use App\Server\Http\Controller\TaskController;
use App\Server\Http\Response\ApiResponse;
use App\Server\Options;
use App\Service\RateLimiter\RateLimiterService;
use App\Service\Telemetry\TraceContext;
use DI\Container;
use OpenTelemetry\API\Trace\SpanKind;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class Router
{
    /**
     * @var array<string, array{
     *     handler: array{0: object, 1: string},
     *     dto: class-string|null,
     *     no_trace: bool,
     *     rate_limit: string|null
     * }>
     */
    private array $routes = [];

    /** @var array<int, mixed> */
    private array $controllers = [];

    public function __construct(
        private readonly Container $container,
        private readonly RateLimiterService $rateLimiter,
        private readonly LoggerInterface $logger,
    ) {
        $this->controllers = [
            $container->get(TaskController::class),
            $container->get(ApiController::class),
            $container->get(HealthController::class),
        ];

        /** @var Options $options */
        $options = $container->get(Options::class);
        if (!$options->appEnv->isProd()) {
            $this->controllers[] = $container->get(BenchmarkController::class);
        }

        $this->scanRoutes();
    }

    /**
     * Dispatches the incoming Swoole HTTP request to the matched controller action.
     * Handles rate limiting, payload decoration, and optional open-telemetry tracing.
     */
    public function dispatch(Request $request, Response $response, Server $server): void
    {
        /** @var array<string, string> $serverParams */
        $serverParams = $request->server;

        $method = $serverParams['request_method'] ?? 'GET';
        $path = $serverParams['request_uri'] ?? '/';
        $routeKey = "{$method}|{$path}";

        // === fast returns ===
        switch (true) {
            case $path === '/ws':
                return;
            case $method === 'OPTIONS':
                $response->status(200);
                $response->end();
                return;
            case !isset($this->routes[$routeKey]):
                $response->status(404);
                $response->end('Not Found');
                return;
        }

        $route = $this->routes[$routeKey];

        try {
            // Declarative Rate Limiting execution
            if (($route['rate_limit'] ?? null) !== null) {
                $ip = $serverParams['remote_addr'] ?? '0.0.0.0';
                if (!$this->rateLimiter->allowed($route['rate_limit'], $ip)) {
                    throw new RateLimitExceededException('Too many requests. Please slow down');
                }
            }

            // Encapsulate execution and parameter binding into a standalone callback
            $executionCallback = function () use ($route, $request, $server) {
                $params = [
                    'request' => $request,
                    'server' => $server,
                ];

                $rawContent = $request->rawContent();
                $payload = !empty($rawContent) ? (json_decode($rawContent, true) ?? []) : [];

                // Contextual parameter injection based on Route metadata
                if (isset($route['dto'])) {
                    $dtoClass = $route['dto'];
                    $params['dto'] = $dtoClass::fromArray($payload);
                } else {
                    $params['payload'] = $payload;
                }

                // Execute the route handler via PHP-DI with autowired method execution
                return $this->container->call($route['handler'], $params);
            };

            // Set default headers
            $this->setDefaultHeaders($response);

            if (empty($route['no_trace'])) {
                $result = TraceContext::run(
                    "http.{$method}",
                    null,
                    SpanKind::KIND_SERVER,
                    [
                        'http.method' => $method,
                        'http.target' => $path,
                    ],
                    $executionCallback,
                    [HttpException::class]
                );
            } else {
                // Trace bypass for infrastructure/health routes
                $result = $executionCallback();
            }

        } catch (HttpException $e) {
            // fromException
            $result = ApiResponse::fromException($e);

        } catch (\Throwable $e) {
            // Log all other errors
            $this->logger->error('Unhandled exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $result = ApiResponse::fromException(new InternalServerErrorException()); // Empty message by intent

        } finally {
            TraceContext::flush();
        }

        // Status
        if ($result instanceof ApiResponse) {
            $response->status($result->status->value);
        }
        // Body
        $json = json_encode($result);
        $response->end(is_string($json) ? $json : '{}');
    }

    /**
     * Scans configured controllers and builds the static routing table.
     * This runs exactly once on Swoole server startup.
     */
    private function scanRoutes(): void
    {
        foreach ($this->controllers as $controller) {
            if (!is_object($controller)) {
                continue;
            }

            $reflection = new ReflectionClass($controller);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $routeAttributes = $method->getAttributes(Route::class);

                foreach ($routeAttributes as $routeAttribute) {
                    /** @var Route $routeAttr */
                    $routeAttr = $routeAttribute->newInstance();

                    $key = "{$routeAttr->method}|{$routeAttr->path}";

                    // Automatically detect DTO from method parameters
                    $dtoClass = null;
                    foreach ($method->getParameters() as $parameter) {
                        $type = $parameter->getType();

                        // Check if parameter has a class type and it's not a system request/response object
                        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                            $className = $type->getName();

                            // Skip system server objects if you typehint them
                            if (!in_array($className, [
                                Request::class,
                                Response::class,
                                Server::class,
                            ], true)) {
                                /** @var class-string $dtoClass */
                                $dtoClass = $className;
                                break;
                            }
                        }
                    }

                    $rateLimitAttributes = $method->getAttributes(RateLimit::class);
                    $limiterName = null;

                    if (!empty($rateLimitAttributes)) {
                        /** @var RateLimit $rateLimitAttr */
                        $rateLimitAttr = $rateLimitAttributes[0]->newInstance();
                        $limiterName = $rateLimitAttr->limiterName;
                    }

                    $this->routes[$key] = [
                        'handler' => [$controller, $method->getName()],
                        'dto' => $dtoClass, // Automatically bound DTO class name
                        'no_trace' => $routeAttr->noTrace,
                        'rate_limit' => $limiterName,
                    ];
                }
            }
        }
    }

    private function setDefaultHeaders(Response $response): void
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type');
        $response->header('Content-Type', 'application/json');
    }
}
