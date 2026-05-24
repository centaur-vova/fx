<?php

declare(strict_types=1);

namespace App\Server\Http\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RateLimit
{
    public function __construct(
        public string $limiterName,
    ) {
    }
}
