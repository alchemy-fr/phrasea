<?php

namespace Alchemy\WebhookBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
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
        $treeBuilder = new TreeBuilder('alchemy_webhook');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('events')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('description')->defaultValue('/')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        $this->getEntitiesConfig($treeBuilder->getRootNode());

        return $treeBuilder;
    }

    private function getEntitiesConfig(NodeDefinition $parent): void
    {
        $parent
            ->children()
                ->arrayNode('normalizer_roles')
                    ->scalarPrototype()->end()
                    ->info('Roles assigned to security token when normalizing object data')
                ->end()
                ->arrayNode('entities')
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->end()
                            ->arrayNode('groups')->scalarPrototype()->end()->defaultValue(['Webhook'])->end()
                            ->arrayNode('create')->canBeDisabled()->end()
                            ->arrayNode('update')->canBeDisabled()->end()
                            ->arrayNode('delete')->canBeDisabled()->end()
                            ->arrayNode('ignoreProperties')->scalarPrototype()->end()->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ;
    }
}
