<?php

declare(strict_types=1);

namespace App\Contract\Support;

enum AppEnv: string
{
    case DEV = 'dev';
    case PROD = 'prod';
    case TEST = 'test';

    public function isDev(): bool
    {
        return $this === self::DEV;
    }

    public function isProd(): bool
    {
        return $this === self::PROD;
    }

    public function isTest(): bool
    {
        return $this === self::TEST;
    }
}
