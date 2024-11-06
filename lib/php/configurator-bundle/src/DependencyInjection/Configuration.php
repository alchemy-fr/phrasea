<?php

namespace Alchemy\ConfiguratorBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_configurator');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('database_url')->defaultValue('%env(CONFIGURATOR_DATABASE_URL)%')->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('bucket_name')->defaultValue('%env(CONFIGURATOR_S3_BUCKET_NAME)%')->end()
                        ->booleanNode('use_path_style_endpoint')->defaultValue('%env(bool:CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT)%')->end()
                        ->scalarNode('endpoint')->defaultValue('%env(CONFIGURATOR_S3_ENDPOINT)%')->end()
                        ->scalarNode('path_prefix')->defaultValue('%env(CONFIGURATOR_S3_PATH_PREFIX)%')->end()
                        ->scalarNode('access_key')->defaultValue('%env(CONFIGURATOR_S3_ACCESS_KEY)%')->end()
                        ->scalarNode('secret_key')->defaultValue('%env(CONFIGURATOR_S3_SECRET_KEY)%')->end()
                        ->scalarNode('region')->defaultValue('%env(CONFIGURATOR_S3_REGION)%')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
