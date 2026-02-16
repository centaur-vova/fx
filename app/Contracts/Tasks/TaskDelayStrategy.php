<?php

declare(strict_types=1);

namespace App\Contracts\Tasks;

interface TaskDelayStrategy
{
    public function __invoke(int $iteration): int;
}
