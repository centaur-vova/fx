<?php

declare(strict_types=1);

namespace App\Service\Provider\Contract;

use Psr\Container\ContainerInterface;
use Swoole\Server;

interface WorkerStartAware
{
    /**
     * Initialize in each worker after process fork
     */
    public function onWorkerStart(ContainerInterface $container, Server $server, int $workerId): void;
}
