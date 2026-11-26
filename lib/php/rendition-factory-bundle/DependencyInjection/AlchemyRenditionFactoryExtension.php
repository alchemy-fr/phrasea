<?php

namespace Alchemy\RenditionFactoryBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyRenditionFactoryExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('alchemy_rendition_factory.download.max_size', $config['download']['max_size']);
        $container->setParameter('alchemy_rendition_factory.download.max_duration', $config['download']['max_duration']);
        $container->setParameter('alchemy_rendition_factory.max_timeout', $config['max_timeout']);
        $container->setParameter('alchemy_rendition_factory.working_directory', $config['working_directory']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }
}
