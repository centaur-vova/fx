<?php

declare(strict_types=1);

namespace App\Exception\Api;

class ApiException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('[API] ' . $message, $code, $previous);
    }
}
