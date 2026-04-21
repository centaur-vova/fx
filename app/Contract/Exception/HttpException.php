<?php

declare(strict_types=1);

namespace App\Contract\Exception;

use App\Contract\Http\HttpStatus;
use Throwable;

interface HttpException extends Throwable
{
    public function getHttpStatus(): HttpStatus;
}
