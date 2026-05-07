<?php

declare(strict_types=1);

namespace App\Service\Task\Processor;

use App\Contract\Task\Processor;
use App\Server\RuntimeContext;

/**
 * "Slow" task execution using Co::sleep
 */
final readonly class PrecisionProcessor implements Processor
{
    public const int STEPS = 11;

    public function __construct(private RuntimeContext $context)
    {
    }

    public function execute(?callable $onProgress = null): string
    {
        $start = microtime(true);

        for ($step = 1; $step <= self::STEPS; $step++) {
            // Sleep or die haha
            $this->context->sleepOrDie(mt_rand(50, 200) / 1000); // 50-200 msec

            if ($onProgress !== null) {
                $progress = (int) round($step / self::STEPS * 100);
                $onProgress(min($progress, 100));
            }
        }

        $elapsed = round(microtime(true) - $start, 2);
        return "execution time: {$elapsed} sec";
    }
}
