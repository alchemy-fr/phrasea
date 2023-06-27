<?php

namespace Alchemy\OAuthServerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('alchemy_oauth_server');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('access_token_lifetime')->defaultValue(7_776_000)->end()
                ->arrayNode('scopes')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('user')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('class')->defaultValue('App\\Entity\\User')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
