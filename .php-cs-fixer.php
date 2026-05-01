<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/config',
        __DIR__ . '/contracts',
        __DIR__ . '/models',
        __DIR__ . '/storage',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notPath('bootstrap.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                             => true,
        'array_syntax'                       => ['syntax' => 'short'],
        'ordered_imports'                    => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'                  => true,
        'trailing_comma_in_multiline'        => true,
        'phpdoc_scalar'                      => true,
        'unary_operator_spaces'              => true,
        'binary_operator_spaces'             => true,
        'blank_line_before_statement'        => ['statements' => ['return']],
        'no_extra_blank_lines'               => true,
        'single_quote'                       => true,
        'cast_spaces'                        => true,
        'declare_strict_types'               => true,
        'no_leading_import_slash'            => true,
        'object_operator_without_whitespace' => true,
        'standardize_not_equals'             => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setCacheFile('/tmp/.php-cs-fixer.cache');
