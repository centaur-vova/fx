<?php

declare(strict_types=1);

namespace App;

use App\Controller\TaskController;
use App\DTO\Http\Request\CreateTasks;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use Throwable;

class Router
{
    /** @var array<string, array{0: object|string, 1: string}> Map: "METHOD|/path" => [Controller, Method] */
    private array $routes = [];

    public function __construct(
        private readonly TaskController $taskController,
    ) {
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->routes = [
            'POST|/api/tasks/create' => [$this->taskController, 'createTasks'],
            'GET|/api/tasks/health' => [$this->taskController, 'health'],
        ];
    }

    public function handle(Request $request, Response $response, Server $server): void
    {
        /** @var array<string, string> $server */
        $server = $request->server;

        $path = $server['request_uri'] ?? '/';
        // fast return for websockets
        if ($path === '/ws') {
            return;
        }

        $method = $server['request_method'] ?? 'GET';
        $key = "$method|$path";

        $this->setDefaultHeaders($response);

        if ($method === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return;
        }

        if (isset($this->routes[$key])) {
            try {
                [$controller, $action] = $this->routes[$key];

                $payload = $this->getJsonPayload($request);

                if ($path === '/api/tasks/create') {
                    $dto = CreateTasks::fromArray($payload);
                    $result = $controller->$action($dto);
                } else {
                    $result = $controller->$action($server);
                }

                $json = json_encode($result);
                $response->end(is_string($json) ? $json : '{}');
            } catch (Throwable $e) {
                $this->sendError($response, $e->getMessage(), 500);
            }
            return;
        }

        $this->sendError($response, 'Not Found', 404);
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

    private function sendError(Response $response, string $msg, int $code): void
    {
        $errorPayload = json_encode(['error' => $msg]);

        $response->status($code);
        $response->end(is_string($errorPayload) ? $errorPayload : '{"error":"Unknown encoding error"}');
    }
}
