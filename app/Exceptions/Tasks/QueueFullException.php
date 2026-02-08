<?php

declare(strict_types=1);

namespace App\Exceptions\Tasks;

use RuntimeException;

class QueueFullException extends RuntimeException
{
    public function __construct(int $capacity)
    {
        parent::__construct("Limit: {$capacity} tasks.");
    }
}
