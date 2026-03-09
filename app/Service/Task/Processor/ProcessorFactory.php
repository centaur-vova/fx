<?php

declare(strict_types=1);

namespace App\Service\Task\Processor;

use App\Contract\Task\Processor;

readonly class ProcessorFactory
{
    public const string MODE_OBSERVATION = 'observation';
    public const string MODE_STRESS = 'stress';

    public function __construct()
    {
    }

    public function get(string $mode): Processor
    {
        return match($mode) {
            self::MODE_OBSERVATION => new PrecisionProcessor(),
            default => new HighLoadProcessor(),
        };
    }
}
