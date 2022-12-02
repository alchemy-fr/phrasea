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
                ->arrayNode('enabled_permissions')
                    ->scalarPrototype()
                    ->defaultValue([
                        'VIEW',
                        'CREATE',
                        'EDIT',
                        'DELETE',
                        'OPERATOR',
                        'OWNER',
                    ])
                    ->info('Explicit enabled permissions, all are enabled by default.')
                    ->example([
                        'VIEW',
                        'EDIT',
                    ])
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
