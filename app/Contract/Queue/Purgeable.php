<?php

declare(strict_types=1);

namespace App\Contract\Queue;

interface Purgeable
{
    public function purge(): void;
}
