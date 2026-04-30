<?php

declare(strict_types=1);

namespace App\Service\Provider\Task;

use App\Contract\Provider\ServiceProvider;
use App\Contract\Provider\WorkerStartAware;
use App\Service\Task\TaskQueueManager;

use function DI\autowire;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Swoole\Server;

class TaskServiceProvider implements ServiceProvider, WorkerStartAware
{
    public function register(ContainerBuilder $builder): array
    {
        return [
            TaskQueueManager::class => autowire(),
        ];
    }

    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void
    {
        // Dont run consumer in task workers
        if ($server->taskworker) {
            return;
        }

        /** @var TaskQueueManager */
        $queueManager = $container->get(TaskQueueManager::class);

        // Only run in worker #0
        if ($workerId === 0) {
            // Start processing
            $queueManager->run($server);
        }
    }
}
