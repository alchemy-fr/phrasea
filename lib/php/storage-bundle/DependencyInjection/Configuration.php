<?php

namespace Alchemy\StorageBundle\DependencyInjection;

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
        $treeBuilder = new TreeBuilder('alchemy_storage');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('upload')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('allowed_types')
                            ->defaultValue('%env(ALLOWED_FILE_TYPES)%')
                            ->info('comma separated values of MIME types, optionally including their extensions')
                            ->example('image/*(.jpg,.jpeg,.png,.heic),application/*(.pdf,.doc,.docx),video/*')
                        ->end()
                        // Not a scalar node: an env placeholder of any type is accepted (e.g. one read from the stack configuration)
                        ->variableNode('max_file_size')
                            ->defaultValue('%env(int:MAX_UPLOAD_FILE_SIZE)%')
                            ->info('maximum size of an uploaded file in bytes, within S3_MAX_OBJECT_SIZE (null or below 1: S3_MAX_OBJECT_SIZE only); checked when the multipart upload is created and when it is completed')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
