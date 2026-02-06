<?php

declare(strict_types=1);

namespace App;

use App\Controllers\TaskController;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Router
{
    /** @var array<string, array> Map: "METHOD|/path" => [Controller, Method] */
    private array $routes = [];

    public function __construct(private TaskController $taskController)
    {
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->routes = [
            'POST|/api/tasks/create' => [$this->taskController, 'createTasks'],
            'GET|/api/tasks/health'  => [$this->taskController, 'health'],
        ];
    }

    public function handle(Request $request, Response $response): void
    {
        $path = $request->server['request_uri'] ?? '/';
        // fast return for websockets
        if ($path === '/ws') {
            return;
        }
        $method = $request->server['request_method'] ?? 'GET';
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
                    $result = $controller->$action(
                        (int) ($payload['count'] ?? 1),
                        (int) ($payload['delay'] ?? 0),
                        (int) ($payload['max_concurrent'] ?? 2)
                    );
                } else {
                    $result = $controller->$action();
                }

                $response->end(json_encode($result));
            } catch (\Throwable $e) {
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

    private function getJsonPayload(Request $request): array
    {
        $raw = $request->getContent();
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    private function sendError(Response $response, string $msg, int $code): void
    {
        $response->status($code);
        $response->end(json_encode(['error' => $msg]));
    }
}
