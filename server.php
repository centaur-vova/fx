<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Server\Kernel;

$kernel = new Kernel(__DIR__);

$kernel->run();
