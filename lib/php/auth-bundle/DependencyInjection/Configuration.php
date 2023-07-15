<?php

namespace Alchemy\AuthBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_auth');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('keycloak')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')->defaultValue('%env(KEYCLOAK_URL)%')->end()
                        ->scalarNode('realm')->defaultValue('%env(KEYCLOAK_REALM)%')->end()
                    ->end()
                ->end()
                ->scalarNode('client_id')->defaultValue('%env(ADMIN_CLIENT_ID)%')->end()
                ->scalarNode('client_secret')->defaultValue('%env(ADMIN_CLIENT_SECRET)%')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
