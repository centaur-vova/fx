<?php

declare(strict_types=1);

namespace App\Contract\Provider;

use Psr\Container\ContainerInterface;
use Swoole\Server;

/**
 * Interface for components that need to react when a Swoole worker starts.
 *
 * This is typically used to reinitialize resources (database connections,
 * cache clients, etc.) that do not survive process forking.
 */
interface WorkerStartAware
{
    /**
     * Initialize in each worker after process fork
     */
    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void;
}
