<?php

namespace Alchemy\CoreBundle\DependencyInjection;

use Alchemy\CoreBundle\DependencyInjection\Compiler\HealthCheckerPass;
use Alchemy\CoreBundle\Health\Checker\DoctrineConnectionChecker;
use Alchemy\CoreBundle\Health\Checker\RabbitMQConnectionChecker;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyCoreExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('alchemy_core.app_base_url', $config['app_base_url']);

        if ($config['healthcheck']['enabled']) {
            $this->loadHealthCheckers($container, $loader);
        }
    }

    private function loadHealthCheckers(ContainerBuilder $container, Loader\YamlFileLoader $loader): void
    {
        $loader->load('healthcheck.yaml');

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['DoctrineBundle'])) {
            $definition = $this->createHealthCheckerDefinition(DoctrineConnectionChecker::class);
            $definition->setArgument('$connectionRegistry', new Reference('doctrine'));
            $container->setDefinition(DoctrineConnectionChecker::class, $definition);
        }

        if (isset($bundles['OldSoundRabbitMqBundle'])) {
            $definition = $this->createHealthCheckerDefinition(RabbitMQConnectionChecker::class);
            $container->setDefinition(RabbitMQConnectionChecker::class, $definition);
        }
    }

    private function createHealthCheckerDefinition(string $class): Definition
    {
        $definition = new Definition($class);
        $definition->setAutowired(true);
        $definition->setAutoconfigured(true);

        $definition->addTag(HealthCheckerPass::TAG);

        return $definition;
    }
}
