<?php

declare(strict_types=1);

namespace App\Server;

use App\DTO\WebSockets\Messages\Metrics;
use App\DTO\WebSockets\Messages\WelcomeMessage;
use App\DTO\WebSockets\WsMessage;
use App\Router;
use App\Services\Monitoring\SystemMonitor;
use App\Services\Tasks\TaskService;
use App\WebSocket\ConnectionPool;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Timer;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class EventHandler
{
    public function __construct(
        private readonly Router $router,
        private readonly ConnectionPool $connectionPool,
        private readonly SystemMonitor $systemMonitor,
        private readonly LoggerInterface $logger,
        private readonly TaskService $taskService,
        private readonly WelcomeMessage $welcomeMessage,
        private readonly int $metricsUpdateIntervalMs,
    ) {
    }

    public function onRequest(Request $request, Response $response): void
    {
        $this->router->handle($request, $response);
    }

    public function onOpen(Server $server, Request $request): void
    {
        // Add new connection to the pool
        $this->connectionPool->add((int) $request->fd);

        // Send details about work count etc
        $this->sendWelcomeMessage($server, $request->fd);

        $this->startMetricsStream($server, $request->fd);
    }

    /**
     * Create a DTO from a raw Swoole frame.
     *
     * We return null instead of throwing Exceptions to avoid overhead
     * in high-concurrency environments (stack trace allocation).
     */
    public function onMessage(Server $server, mixed $frame): void
    {
        // Make sure that Frame is received
        if (!($frame instanceof Frame)) {
            return;
        }

        // Decode
        $payload = json_decode((string) $frame->data, true);
        if (!is_array($payload)) {
            return;
        }

        // Map to DTO
        $wsMessage = WsMessage::fromArray($payload);
        if ($wsMessage === null) {
            return;
        }

        switch ($wsMessage->event) {
            case 'ping':
                $this->send($server, $frame->fd, new WsMessage('pong', $wsMessage->data));
                break;
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->connectionPool->remove($fd);
    }

    private function sendWelcomeMessage(Server $server, int $fd): void
    {
        $this->send($server, $fd, new WsMessage(
            event: 'welcome',
            data: $this->welcomeMessage,
        ));
    }

    private function startMetricsStream(Server $server, int $fd): void
    {
        $interval = $this->metricsUpdateIntervalMs;

        Timer::tick($interval, function (int $timerId) use ($server, $fd): void {
            // In case of disconnect clear the timer
            if (!$server->exists($fd)) {
                Timer::clear($timerId);
                return;
            }

            // Collect system stats
            $systemStats = $this->systemMonitor->capture();
            // No of active tasks
            $taskNum = $this->taskService->getTaskNum();

            $data = new Metrics(
                taskNum:     $taskNum,
                connections: $systemStats->connections,
                memoryMb:    $systemStats->memoryMb,
                cpuUsage:    $systemStats->cpuUsage,
            );

            $this->send($server, $fd, new WsMessage(
                event: 'metrics.update',
                data: $data,
            ));
        });
    }

    /**
     * Send a standardized payload to the client.
     */
    private function send(Server $server, int $fd, WsMessage $wsMessage): void
    {
        $result = $server->push($fd, json_encode($wsMessage));
        if ($result === false) {
            $this->logger->warning('WS: push failed', [
                'fd' => $fd,
                'event' => $wsMessage->event,
                'error_code' => swoole_last_error(),
            ]);
        }
    }
}
