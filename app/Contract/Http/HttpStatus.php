<?php

declare(strict_types=1);

namespace App\Contract\Http;

enum HttpStatus: int
{
    case OK = 200;
    case BAD_REQUEST = 400;
    case NOT_FOUND = 404;
    case TOO_MANY_REQUESTS = 429;
    case INTERNAL_SERVER_ERROR = 500;
}
