<?php

declare(strict_types=1);

namespace App\Contract\Cache;

enum CacheDriver: string
{
    case SWOOLE_TABLE = 'swoole_table';
    case REDIS = 'redis';
}
