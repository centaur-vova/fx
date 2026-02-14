<?php

declare(strict_types=1);

namespace App\Contracts\Tasks;

interface Processor
{
    public function execute(?callable $onProgress = null): string;
}
