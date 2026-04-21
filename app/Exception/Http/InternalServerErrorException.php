<?php

declare(strict_types=1);

namespace App\Exception\Http;

use App\Contract\Exception\HttpException;
use App\Contract\Http\HttpStatus;
use Exception;

class InternalServerErrorException extends Exception implements HttpException
{
    public function getHttpStatus(): HttpStatus
    {
        return HttpStatus::INTERNAL_SERVER_ERROR;
    }
}
