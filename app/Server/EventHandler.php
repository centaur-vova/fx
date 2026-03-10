<?php

declare(strict_types=1);

namespace App\Server;

use App\Contract\Monitoring\TaskCounter;
use App\DTO\WebSocket\Message\InternalEnvelope;
use App\DTO\WebSocket\Message\Metrics;
use App\DTO\WebSocket\Message\WelcomeMessage;
use App\DTO\WebSocket\WsMessage;
use App\Router;
use App\Service\Monitoring\SystemMonitor;
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
        private readonly TaskCounter $taskCounter,
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
        $fd = (int) $request->fd;
        $workerId = (int) $server->worker_id;

        // Add new connection to the pool
        $this->connectionPool->add($fd, $workerId);

        $this->logger->info('Client connected', [
            'fd' => $fd,
            'worker' => $workerId,
        ]);

        $this->sendWelcomeMessage($server, $fd);
        $this->startMetricsStream($server, $fd);
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
                $this->send($server, $frame->fd, InternalEnvelope::wrap('pong', $wsMessage->data));
                break;
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->connectionPool->remove($fd);

        $this->logger->info('Client disconnected', [
            'fd' => $fd,
        ]);
    }

    private function sendWelcomeMessage(Server $server, int $fd): void
    {
        $this->send($server, $fd, InternalEnvelope::wrap('welcome', $this->welcomeMessage));
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
            $taskNum = $this->taskCounter->get();

            $data = new Metrics(
                taskNum:     $taskNum,
                connections: $systemStats->connections,
                memoryMb:    $systemStats->memoryMb,
                cpuUsage:    $systemStats->cpuUsage,
            );

            $this->send($server, $fd, InternalEnvelope::wrap('metrics.update', $data));
        });
    }

    /**
     * Send a standardized payload to the client.
     */
    private function send(Server $server, int $fd, InternalEnvelope $envelope): void
    {
        $payload = $envelope->getFinalPayload();
        $opcode = $envelope->getOpcode();

        $server->push($fd, $payload, $opcode);
    }
}
