<?php

declare(strict_types=1);

namespace App\Contract\Storage;

interface RateLimiterStorage extends TtlKeyValueStorage
{
}
