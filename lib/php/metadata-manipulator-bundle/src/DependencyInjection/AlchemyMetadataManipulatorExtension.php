<?php

namespace Alchemy\MetadataManipulatorBundle\DependencyInjection;

use Alchemy\MetadataManipulatorBundle\Exception\BadConfigurationException;
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

        if (!empty($config['classes_directory'])) {
            $dir = __DIR__ . '/../../../../' . $config['classes_directory'];
            @mkdir($dir, 0754, true);
            $rdir = realpath($dir);
            if(!$rdir || !file_exists($rdir) || !is_dir($rdir) || !is_writable($rdir)) {
                throw new BadConfigurationException(sprintf("cannot access/create classes_directory \"%s\".", $dir));
            }
            $container->setParameter('alchemy_metadata_manipulator.config', $config);
        }
        else {
            throw new BadConfigurationException("missing \"classes_directory\" configuration for MetadataManipulator bundle.");
        }
    }
}
