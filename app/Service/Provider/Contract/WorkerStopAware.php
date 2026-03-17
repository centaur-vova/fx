<?php

declare(strict_types=1);

namespace App\Service\Provider\Contract;

use Psr\Container\ContainerInterface;

interface WorkerStopAware
{
    /**
     * Cleanup when worker stops
     *
     * @param ContainerInterface $container
     * @param int $workerId
     * @return void
     */
    public function onWorkerStop(ContainerInterface $container, int $workerId): void;
}
