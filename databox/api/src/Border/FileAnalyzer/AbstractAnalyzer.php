<?php

declare(strict_types=1);

namespace App\Border\FileAnalyzer;

use Alchemy\RenditionFactory\Transformer\Documentation;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

abstract readonly class AbstractAnalyzer implements AnalyzerInterface
{
    public function validateConfiguration(array $config): void
    {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
    }

    public function getDocumentation(): Documentation
    {
        $treeBuilder = FileAnalyzerConfigHelper::createBaseTree(static::getName());
        $this->buildConfiguration($treeBuilder->getRootNode()->children());

        return new Documentation(
            $treeBuilder,
            $this->getDocumentationHeader()
        );
    }

    protected function getDocumentationHeader(): string
    {
        return '';
    }
}
