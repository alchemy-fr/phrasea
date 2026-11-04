<?php

namespace Alchemy\ESBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('alchemy_es');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('async')
                    ->defaultTrue()
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('index_prefix')
                            ->defaultValue('')
                            ->info('Only physical indices and aliases whose name starts with this prefix are shown and manageable in the EasyAdmin screens (empty: everything). Typically the same prefix as your FOS Elastica index names, e.g. "%env(ELASTICSEARCH_INDEX_PREFIX)%".')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
