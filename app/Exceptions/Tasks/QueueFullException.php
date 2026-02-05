<?php

declare(strict_types=1);

namespace App\Exceptions\Tasks;

use RuntimeException;

class QueueFullException extends RuntimeException
{
    public function __construct(int $capacity)
    {
        parent::__construct("The queue capacity has been exceeded! Limit: {$capacity} tasks.");
    }
}
