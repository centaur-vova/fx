<?php

declare(strict_types=1);

namespace App\Contract\Task;

/**
 * Defines available semaphore driver implementations.
 *
 * - API: Uses Go + Redis distributed semaphore
 * - SHARED: Uses PHP Swoole shared memory atomic
 */
enum SemaphoreDriver: string
{
    case API = 'api';
    case SHARED = 'shared';

    /**
     * Returns all semaphore driver values as an array of strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
