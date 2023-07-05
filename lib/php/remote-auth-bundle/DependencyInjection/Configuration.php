<?php

namespace Alchemy\RemoteAuthBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_remote_auth');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('admin_auth')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('client_id')->defaultValue('%env(ADMIN_CLIENT_ID)%')->end()
                        ->scalarNode('client_secret')->defaultValue('%env(ADMIN_CLIENT_SECRET)%')->end()
                    ->end()
                ->end()
                ->arrayNode('login_forms')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('route_name')->defaultValue('login')->end()
                            ->scalarNode('default_target_path')->defaultValue('/')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
