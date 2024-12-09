<?php

namespace Alchemy\RenditionFactory\Transformer;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class TransformerConfigHelper
{
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
