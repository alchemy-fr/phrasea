<?php

namespace Alchemy\RenditionFactoryBundle\DependencyInjection;

use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\Transformer\TransformerModuleInterface;
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
        $treeBuilder = new TreeBuilder('alchemy_rendition_factory');
        // @formatter:off
        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('download')
                    ->info('Limits of each remote file downloaded by the renditions (download module, watermarks)')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_size')
                            ->info('In bytes')
                            ->min(1)
                            ->defaultValue(TransformationContextFactory::DEFAULT_MAX_DOWNLOAD_SIZE)
                        ->end()
                        ->floatNode('max_duration')
                            ->info('In seconds, for the whole transfer')
                            ->min(1)
                            ->defaultValue(TransformationContextFactory::DEFAULT_MAX_DOWNLOAD_DURATION)
                        ->end()
                    ->end()
                ->end()
                ->integerNode('max_timeout')
                    ->info('Highest "timeout" (in seconds) a rendition definition may set on a module (ffmpeg modules, document_to_pdf); also the default timeout of the ffmpeg modules')
                    ->min(1)
                    ->defaultValue(TransformerModuleInterface::DEFAULT_MAX_TIMEOUT)
                ->end()
                ->scalarNode('working_directory')
                    ->info('Base directory of the working files; the system temporary directory by default')
                    ->defaultNull()
                ->end()
            ->end()
        ;
        // @formatter:on

        return $treeBuilder;
    }
}
