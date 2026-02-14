<?php

declare(strict_types=1);

namespace App\Services\Tasks\Processors;

use App\Contracts\Tasks\Processor;
use Swoole\Coroutine as Co;

/**
 * "Slow" task execution using Co::sleep
 */
class PrecisionProcessor implements Processor
{
    public const STEPS = 4;

    public function execute(?callable $onProgress = null): string
    {
        $start = microtime(true);

        for ($step = 1; $step <= self::STEPS; $step++) {
            Co::sleep(mt_rand(800, 1300) / 1000);
            if ($onProgress !== null) {
                $onProgress($step * 25);
            }
        }

        $elapsed = round(microtime(true) - $start, 2);
        return "execution time: {$elapsed} sec";
    }
}
