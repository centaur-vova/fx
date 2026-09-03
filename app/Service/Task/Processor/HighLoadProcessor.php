<?php

declare(strict_types=1);

namespace App\Service\Task\Processor;

use App\Contract\Task\Processor;
use Swoole\Coroutine as Co;

/**
 * Fast hash calculations
 */
class HighLoadProcessor implements Processor
{
    public function execute(?callable $onProgress = null): string
    {
        $data = hash('sha256', random_bytes(32));

        if ($onProgress !== null) {
            $onProgress(100);
        }

        return "hash: {$data}";
    }
}
