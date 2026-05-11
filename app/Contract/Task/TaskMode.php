<?php

declare(strict_types=1);

namespace App\Contract\Task;

enum TaskMode: string
{
    case OBSERVATION = 'observation';
    case STRESS = 'stress';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
