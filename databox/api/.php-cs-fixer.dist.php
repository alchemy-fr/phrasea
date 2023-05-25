<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('__lib')
    ->exclude('var')
    ->exclude('src/Migrations')
    ->exclude('vendor');

$config = new PhpCsFixer\Config();

return $config->setRules([
        '@Symfony' => true,
        'no_unused_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_extra_blank_lines' => true,
        'no_spaces_around_offset' => true,
        'no_spaces_inside_parenthesis' => true,
        'no_trailing_whitespace' => true,
        'single_blank_line_at_eof' => true,
        'phpdoc_separation' => ['groups' => [['ORM\\*'], ['Assert\\*']]],
    ])
    ->setFinder($finder);
