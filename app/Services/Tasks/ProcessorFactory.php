<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Contracts\Tasks\Processor;
use App\Services\Tasks\Processors\HighLoadProcessor;
use App\Services\Tasks\Processors\PrecisionProcessor;

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
