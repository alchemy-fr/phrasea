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
                ->floatNode('deduplication_ttl')
                    ->defaultValue(900)
                    ->min(0)
                    ->info('Asynchronous mode: an indexation message identical to one waiting in the queue is dropped (coalescing). The deduplication lock is released when the message is received, or after this TTL in seconds if it is lost. 0 disables it. Requires the Lock component and framework.lock.')
                ->end()
                ->scalarNode('deduplication_key_prefix')
                    ->defaultValue('')
                    ->info('Prefix of the deduplication lock keys: the deployments sharing a lock store (e.g. a Redis server) must each have their own, otherwise they drop each other\'s messages. Typically the prefix of your FOS Elastica index names, e.g. "%env(ELASTICSEARCH_INDEX_PREFIX)%".')
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
