<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
    ])
    ->withPhpSets(php84: true)
        // Base rules for now
    ->withSkip([
        // Disable renaming
        'Rector\Naming\*',
        'Rector\Naming\Rector\Property\*',
        'Rector\Naming\Rector\ClassMethod\*',

        ReadOnlyClassRector::class,
        // For anonymous classes
        ReadOnlyClassRector::class => [
            '*.php',
        ],

        ArrayToFirstClassCallableRector::class,
    ]);
