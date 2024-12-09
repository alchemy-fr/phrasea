<?php

namespace Alchemy\RenditionFactory\Transformer;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Documentation
{
    /** @var Documentation[] */
    private array $children;

    public function __construct(private readonly TreeBuilder $treeBuilder, private readonly string $header = '', private readonly string $footer = '')
    {
        $this->children = [];
    }

    public function addChild(Documentation $child): void
    {
        $this->children[] = $child;
    }

    public function getTreeBuilder(): TreeBuilder
    {
        return $this->treeBuilder;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    public function getFooter(): string
    {
        return $this->footer;
    }
}
