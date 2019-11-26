<?php

namespace Alchemy\NotifyBundle\DependencyInjection;

use Alchemy\NotifyBundle\Notify\Notifier;
use Alchemy\NotifyBundle\Notify\NotifierInterface;
use Alchemy\NotifyBundle\Notify\NullNotifier;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyNotifyExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('notify_base_url_internal', $config['notify_base_url']);

        if ('test' === $container->getParameter('kernel.environment')) {
            $def = new Definition(NullNotifier::class);
            $container->setDefinition(NullNotifier::class, $def);
            $container->setAlias(NotifierInterface::class, NullNotifier::class);
        } else {
            $container->setAlias(NotifierInterface::class, Notifier::class);
        }
    }
}
