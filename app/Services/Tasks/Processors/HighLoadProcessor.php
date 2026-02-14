<?php

declare(strict_types=1);

namespace App\Services\Tasks\Processors;

use App\Contracts\Tasks\Processor;
use Swoole\Coroutine as Co;

/**
 * Fast hash calculations
 */
class HighLoadProcessor implements Processor
{
    public const STEPS = 4;
    public const ITERATIONS = 100;

    public function execute(?callable $onProgress = null): string
    {
        $start = microtime(true);

        $data = random_bytes(32);
        for ($step = 1; $step <= self::STEPS; $step++) {
            for ($i = 0; $i < self::ITERATIONS; $i++) {
                $data = hash('sha256', $data);
            }

            // Allow others to do their work
            Co::sleep(0.001);

            if ($onProgress !== null) {
                $onProgress($step * 25);
            }
        }
        return "hash: {$data}";
    }
}
