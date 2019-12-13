<?php

namespace Alchemy\ReportBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_report');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('app_id')->defaultValue('%env(APP_ID)%')->end()
                ->scalarNode('app_name')->isRequired()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
