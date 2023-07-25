<?php

namespace Alchemy\CoreBundle\DependencyInjection;

use Alchemy\CoreBundle\DependencyInjection\Compiler\HealthCheckerPass;
use Alchemy\CoreBundle\Health\Checker\DoctrineConnectionChecker;
use Alchemy\CoreBundle\Health\Checker\RabbitMQConnectionChecker;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyCoreExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.yaml');
        $this->loadFixtures($container, $loader);

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['MonologBundle'])) {
            $loader->load('monolog.yaml');
        }

        if (!empty($config['app_url'])) {
            $container->setParameter('alchemy_core.app_url', $config['app_url']);
            $loader->load('router_listener.yaml');
        }

        if ($config['healthcheck']['enabled']) {
            $loader->load('healthcheck.yaml');
            $this->loadHealthCheckers($container);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        $env = $container->getParameter('kernel.environment');

        if (isset($bundles['MonologBundle'])) {
            $configFile = sprintf(
                '%s/monolog/%s.yaml',
                __DIR__.'/../Resources/config',
                $env
            );

            if (file_exists($configFile)) {
                $container->prependExtensionConfig('monolog', Yaml::parseFile($configFile)['monolog']);
            }
        }
    }

    private function loadHealthCheckers(ContainerBuilder $container): void
    {
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

    private function loadFixtures(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['AlchemyStorageBundle'], $bundles['HautelookAliceBundle'])) {
            $loader->load('fixtures.yaml');
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
