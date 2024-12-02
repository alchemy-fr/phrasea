<?php

namespace Alchemy\CoreBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_core');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('app_name')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('app_id')->defaultValue('%env(APP_ID)%')->cannotBeEmpty()->end()
                ->scalarNode('app_url')->defaultNull()->end()
                ->arrayNode('healthcheck')
                    ->canBeEnabled()
                    ->children()
                    ->end()
                ->end()
                ->arrayNode('notification')
                    ->canBeDisabled()
                    ->children()
                    ->end()
                ->end()
                ->arrayNode('pusher')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('disabled')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('host')
                            ->cannotBeEmpty()
                            ->defaultValue('%env(SOKETI_HOST)%')
                        ->end()
                        ->scalarNode('key')
                            ->cannotBeEmpty()
                            ->defaultValue('%env(SOKETI_KEY)%')
                        ->end()
                        ->scalarNode('secret')
                            ->cannotBeEmpty()
                            ->defaultValue('%env(SOKETI_SECRET)%')
                        ->end()
                        ->scalarNode('appId')
                            ->cannotBeEmpty()
                            ->defaultValue('%env(SOKETI_APP_ID)%')
                        ->end()
                        ->booleanNode('verifySsl')
                            ->defaultValue('%env(bool:VERIFY_SSL)%')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
