<?php

namespace Alchemy\RenditionFactory\Transformer;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class TransformerConfigHelper
{
    /** @var Documentation[] */
    private array $children = [];

    public function __construct(private readonly TreeBuilder $treeBuilder, private readonly string $header = '', private readonly string $footer = '')
    {
        $this->children = [];
    }

    public function addChild(TransformerConfigHelper $child): void
    {
        $this->children[] = $child;
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

    /**
     * helper to create a base tree for a module, including common options.
     */
    public static function createBaseTree(string $name): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        $rootNode = $treeBuilder->getRootNode();
        // @formatter:off
        $rootNode
            ->children()
                ->scalarNode('module')
                    ->isRequired()
                    ->defaultValue($name)
                ->end()
                ->scalarNode('description')
                    ->info('Description of the module action')
                ->end()
                ->scalarNode('enabled')
                    ->defaultTrue()
                    ->info('Whether to enable this module')
                ->end()
            ->end()
        ;
        // @formatter:on

        return $treeBuilder;
    }
}
