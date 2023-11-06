<?php

namespace Alchemy\AdminBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_admin');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('service')
                    ->isRequired()
                    ->children()
                        ->scalarNode('title')->end()
                        ->scalarNode('name')
                            ->isRequired()
                            ->example('expose')
                            ->info('The name of the service hosting the admin (in order to access the config node)')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
