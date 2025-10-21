<?php

namespace App\Border\Analyzer;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;

abstract readonly class AbstractAnalyzer implements AnalyzerInterface
{
    public function validateConfiguration(array $config): void
    {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
    }
}
