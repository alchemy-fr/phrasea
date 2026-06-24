<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/src/Elasticsearch/AQL/AQLGrammar.php',
    ])
//    ->withSets([SetList::DEAD_CODE])
    ->withPhpSets(php85: true)
    ->withRules([
        DeclareStrictTypesRector::class,
    ]);
