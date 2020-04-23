<?php

namespace Alchemy\AclBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('alchemy_acl');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('auth')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('client_id')->defaultValue('%env(ADMIN_CLIENT_ID)%_%env(ADMIN_CLIENT_RANDOM_ID)%')->end()
                        ->scalarNode('client_secret')->defaultValue('%env(ADMIN_CLIENT_SECRET)%')->end()
                    ->end()
                ->end()
                ->arrayNode('objects')
                ->useAttributeAsKey('key')
                    ->prototype('scalar')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
