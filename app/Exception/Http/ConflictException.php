<?php

declare(strict_types=1);

namespace App\Exception\Http;

use App\Contract\Exception\HttpException;
use App\Contract\Http\HttpStatus;

class ConflictException extends \RuntimeException implements HttpException
{
    public function getHttpStatus(): HttpStatus
    {
        return HttpStatus::CONFLICT;
    }
}
