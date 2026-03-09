<?php

declare(strict_types=1);

namespace App\Contract\Task;

interface Processor
{
    public function execute(?callable $onProgress = null): string;
}
