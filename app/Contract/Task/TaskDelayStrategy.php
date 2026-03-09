<?php

declare(strict_types=1);

namespace App\Contract\Task;

interface TaskDelayStrategy
{
    public function __invoke(int $iteration): int;
}
