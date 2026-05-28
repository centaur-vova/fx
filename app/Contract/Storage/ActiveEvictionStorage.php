<?php

declare(strict_types=1);

namespace App\Contract\Storage;

interface ActiveEvictionStorage
{
    public function cleanExpired(): int;
}
