<?php

namespace Alchemy\TestBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_test');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('db_path')->defaultValue('%kernel.cache_dir%/data.db')->end()
                ->scalarNode('db_filled_path')->defaultValue('%kernel.cache_dir%/data.filled.db')->end()
                ->scalarNode('db_empty_path')->defaultValue('%kernel.cache_dir%/data.empty.db')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
