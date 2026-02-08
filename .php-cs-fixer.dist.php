<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'storage',
        'bootstrap/cache',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRules([
        // Follow PSR-12 coding standard
        '@PSR12' => true,

        // Force strict types declaration in every file
        'declare_strict_types' => true,

        // Use short array syntax [] instead of array()
        'array_syntax' => ['syntax' => 'short'],

        // Sort imports alphabetically for consistency
        'ordered_imports' => ['sort_algorithm' => 'alpha'],

        // Remove unused use statements
        'no_unused_imports' => true,

        // Add trailing commas in multiline elements (arrays, match, params) for clean diffs
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'parameters', 'match']
        ],

        // Ensure single space around binary operators (no alignment to keep diffs clean)
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],

        // Remove unnecessary blank lines (extra lines, throw, use, etc.)
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'throw', 'use', 'break', 'continue', 'return']
        ],

        // Use strict comparisons (=== and !==) instead of loose ones
        'strict_comparison' => true,

        // Force strict parameters in function calls
        'strict_param' => true,

        // Use nullable type hints (e.g., ?string) instead of verbose unions
        'compact_nullable_typehint' => true,

        // Standardize spaces around type declarations
        'type_declaration_spaces' => true,

        // Force multiline arguments to be on separate lines for readability
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline'
        ],

        // Ensure no useless else statements after return/throw
        'no_useless_else' => true,

        // Standardize string quotes to single unless interpolation is needed
        'single_quote' => true,

        // Remove trailing whitespace at the end of lines
        'no_trailing_whitespace' => true,

        // Ensure there is only one blank line between classes/methods
        'class_attributes_separation' => [
            'elements' => ['method' => 'one']
        ],

        // Ensure return types are always present
        'void_return' => true,
        'fully_qualified_strict_types' => false,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
