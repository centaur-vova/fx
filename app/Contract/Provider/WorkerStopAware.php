<?php

declare(strict_types=1);

namespace App\Contract\Provider;

use Psr\Container\ContainerInterface;

/**
 * Interface for components that need to clean up when a Swoole worker stops.
 */
interface WorkerStopAware
{
    /**
     * Cleanup when worker stops
     */
    public function onWorkerStop(ContainerInterface $container, int $workerId): void;
}
