<?php

namespace Alchemy\MetadataManipulatorBundle\DependencyInjection;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
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
        $treeBuilder = new TreeBuilder('alchemy_metadata_manipulator');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('debug')->defaultFalse()->end()
                ->scalarNode('classes_directory')
                    ->defaultValue('%kernel.cache_dir%/phpexiftool')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('converter_url')
                    ->info('URL of the conversion container; when not empty, exiftool runs there through "converter_exiftool"')
                    ->defaultNull()
                ->end()
                ->scalarNode('converter_exiftool')
                    ->info('The convert-exec wrapper of exiftool, in the worker image')
                    ->defaultValue(MetadataManipulator::CONVERTER_EXIFTOOL)
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
