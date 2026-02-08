<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
    ])
    // Target PHP 8.4 engine
    ->withPhpSets(php84: true)
    // Enable automated type declarations (Return types, argument types, etc.)
    ->withSets([
        SetList::TYPE_DECLARATION,
    ])
    // Global import settings
    ->withImportNames(importNames: false, importDocBlockNames: false)
    ->withSkip([
        AddArrowFunctionReturnTypeRector::class,

        // Disable renaming
        'Rector\Naming\*',
        'Rector\Naming\Rector\Property\*',
        'Rector\Naming\Rector\ClassMethod\*',

        ReadOnlyClassRector::class,
        ReadOnlyClassRector::class => [
            '*.php',
        ],

        ArrayToFirstClassCallableRector::class,
    ]);
