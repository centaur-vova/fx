<?php

declare(strict_types=1);

namespace App\Contract\Support;

interface FromArray
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self;
}
