<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath('var')
    ->notPath('vendor')
    ->notPath('.symfony')
    ->notPath('migrations');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        'php_unit_test_annotation' => false,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['const', 'function', 'class'],
            'sort_algorithm' => 'alpha',
        ],
        'single_line_throw' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'array_indentation' => true,
        'array_push' => true,
        'blank_line_before_statement' => [
            'statements' => [
                'case',
                'default',
                'declare',
                'do',
                'exit',
                'foreach',
                'for',
                'goto',
                'if',
                'return',
                'switch',
                'throw',
                'try',
                'while',
                'yield'
            ],
        ],
        'lambda_not_used_import' => true,
        'no_trailing_whitespace_in_comment' => true,
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'phpdoc_indent' => true,
        'phpdoc_order' => true,
        'phpdoc_scalar' => true,
        'phpdoc_summary' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'alpha',
        ],
        'psr_autoloading' => true,
        'simple_to_complex_string_variable' => true,
        'static_lambda' => true,
        'string_implicit_backslashes' => true,
        'string_line_ending' => true,
        'visibility_required' => [
            'elements' => ['method', 'property'],
        ],
        'void_return' => true,
    ])
    ->setFinder($finder);
