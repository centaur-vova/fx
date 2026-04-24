<?php

declare(strict_types=1);

namespace App\Domain\Cache\Contract;

use App\Contract\Storage\TtlKeyValueStorage;

interface CacheStorage extends TtlKeyValueStorage
{
};
