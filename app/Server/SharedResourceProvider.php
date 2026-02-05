<?php

declare(strict_types=1);

namespace App\Server;

use App\Config;
use App\WebSocket\ConnectionPool;
use Swoole\Atomic;

class SharedResourceProvider
{
    /**
     * Boot shared memory resources
     */
    public static function boot(Config $config): array
    {
        // Connections table
        $size = $config->getInt('WS_TABLE_SIZE', 1024);
        $connections = ConnectionPool::configureAndCreateTable($size);

        // CPU cores info
        $cpuCores = (int) shell_exec('nproc') ?: 1;

        return [
            'connections' => $connections,
            'task_counter' => new Atomic(0),
            'cpu_cores' => $cpuCores,
        ];
    }
}
