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
                ->arrayNode('worker')
                    ->children()
                        ->arrayNode('queue')
                            ->isRequired()
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('rabbit')
                            ->isRequired()
                            ->children()
                                ->scalarNode('host')
                                    ->isRequired()
                                    ->defaultValue('%env(RABBITMQ_HOST)%')
                                ->end()
                                ->scalarNode('port')
                                    ->isRequired()
                                    ->defaultValue('%env(RABBITMQ_PORT)%')
                                ->end()
                                ->scalarNode('user')
                                    ->isRequired()
                                    ->defaultValue('%env(RABBITMQ_USER)%')
                                ->end()
                                ->scalarNode('password')
                                    ->isRequired()
                                    ->defaultValue('%env(RABBITMQ_PASSWORD)%')
                                ->end()
                                ->scalarNode('vhost')
                                    ->isRequired()
                                    ->defaultValue('%env(RABBITMQ_VHOST)%')
                                ->end()                
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
