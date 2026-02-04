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
        $method = $request->server['request_method'] ?? 'GET';
        $key = "$method|$path";

        $this->setDefaultHeaders($response);

        if ($method === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return;
        }

        if ($method === 'GET' && $path === '/') {
            $this->serveHtml($response, 'index.html');
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

        if ($method === 'GET') {
            $staticPaths = ['/dist/style.min.css', '/dist/app.min.js', '/favicon.ico'];

            if (in_array($path, $staticPaths, true)) {
                $filePath = __DIR__ . '/../public' . $path;
                if (file_exists($filePath)) {
                    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                    $mime = [
                        'css' => 'text/css',
                        'js' => 'application/javascript',
                        'ico' => 'image/x-icon',
                    ][$ext] ?? 'text/plain';

                    $response->header('Content-Type', $mime);
                    $response->end(file_get_contents($filePath));
                    return;
                }
            }
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

    private function serveHtml(Response $response, string $filename): void
    {
        $response->header('Content-Type', 'text/html');
        $file = __DIR__ . '/../public/' . $filename;

        if (file_exists($file)) {
            $response->end(file_get_contents($file));
        } else {
            $this->sendError($response, "Entry file $filename not found", 500);
        }
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
