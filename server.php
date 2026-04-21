<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Server\Kernel;

try {
    $kernel = new Kernel(__DIR__);
    $kernel->run();
} catch (\Throwable $e) {
    $message = $e->getMessage();
    $code = $e->getCode();
    echo "\033[31m[ERROR]\033[0m {$message}\n";
    exit(1);
}
