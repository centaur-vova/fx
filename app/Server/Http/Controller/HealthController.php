<?php

declare(strict_types=1);

namespace App\Server\Http\Controller;

use App\Contract\Storage\CacheStorage;
use App\Server\Http\Attribute\Route;
use App\Server\Http\Response\ApiResponse;
use App\Server\Http\Response\HealthResponse;
use App\Service\Api\BalancerApi;
use Swoole\Http\Server;

final readonly class HealthController
{
    public function __construct(
        private CacheStorage $cache,
        private BalancerApi $balancerApi,
    ) {
    }

    /**
     * TODO: move to the separate controller
     * TODO: add caching
     */
    #[Route(method: 'GET', path: '/health', noTrace: true)]
    public function health(Server $server): ApiResponse
    {
        // Balancer's health
        $balancerHealth = $this->balancerApi->health();

        // PHP Swoole specific data
        /** @var array{tasking_num: int, task_worker_num: int} $stats */
        $stats = $server->stats();
        $taskLastCreated = (int) $this->cache->get('task-last-created');

        $data = new HealthResponse(
            status: 'ok',
            phpVersion: PHP_VERSION,
            memoryMb: round(memory_get_usage(false) / 1024 / 1024, 2),
            tasksInProgress: $stats['tasking_num'],
            taskWorkers: $stats['task_worker_num'],
            idleWorkers: $stats['task_worker_num'] - $stats['tasking_num'],
            taskLastCreated: $taskLastCreated,
            balancerHealth: $balancerHealth,
        );

        return ApiResponse::ok('Health', $data);
    }
}
