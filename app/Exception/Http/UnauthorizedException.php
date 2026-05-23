<?php

declare(strict_types=1);

namespace App\Exception\Http;

use App\Contract\Exception\HttpException;
use App\Contract\Http\HttpStatus;

class UnauthorizedException extends \RuntimeException implements HttpException
{
    public function getHttpStatus(): HttpStatus
    {
        return HttpStatus::UNAUTHORIZED;
    }
}
