<?php

namespace Alchemy\ESBundle\DependencyInjection;

use Alchemy\ESBundle\Indexer\IndexableDependenciesResolverInterface;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyESExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $def = $container->getDefinition(SearchIndexer::class);
        $def->setArgument('$direct', !$config['async']);

        $container->registerForAutoconfiguration(IndexableDependenciesResolverInterface::class)
            ->addTag(IndexableDependenciesResolverInterface::TAG)
        ;
    }
}
