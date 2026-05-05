<?php

declare(strict_types=1);

namespace App\Contract\Task;

enum SemaphoreDriver: string
{
    case API = 'api';
    case SHARED = 'shared';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
