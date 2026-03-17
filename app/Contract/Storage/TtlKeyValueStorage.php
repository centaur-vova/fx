<?php

declare(strict_types=1);

namespace App\Contract\Storage;

interface TtlKeyValueStorage extends KeyValueStorage
{
    public function cleanExpired(): int;

    public function startCleaner(int $interval): void;
}
