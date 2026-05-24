<?php

declare(strict_types=1);

namespace App\Server\Http\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    public function __construct(
        public string $method,
        public string $path,
        public bool $noTrace = false,
    ) {
    }
}
