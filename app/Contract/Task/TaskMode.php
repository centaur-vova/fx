<?php

declare(strict_types=1);

namespace App\Contract\Task;

/**
 * Task execution mode.
 *
 * - OBSERVATION: Simulated work with artificial delays
 * - STRESS: CPU-intensive work (hash computation)
 */
enum TaskMode: string
{
    case OBSERVATION = 'observation';
    case STRESS = 'stress';

    /**
     * Returns all task mode values as an array of strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
