<?php

namespace Alchemy\MetadataManipulatorBundle\DependencyInjection;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyMetadataManipulatorExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $container->setParameter('alchemy_mm.classes_directory', $config['classes_directory']);

        $def = $container->getDefinition(MetadataManipulator::class);
        $def->setArgument('$classesDirectory', $config['classes_directory']);
        $def->setArgument('$debug', $config['debug']);
    }
}
