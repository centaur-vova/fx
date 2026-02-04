<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Log\AbstractLogger;

class StdoutLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $time = microtime(true);
        $date = date('H:i:s', (int)$time);
        $ms = sprintf("%03d", ($time - (int)$time) * 1000);

        $output = sprintf(
            "[%s.%s] [%s] %s %s\n",
            $date,
            $ms,
            strtoupper((string)$level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        echo $output;
    }
}
