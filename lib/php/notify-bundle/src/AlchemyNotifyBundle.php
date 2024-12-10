<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle;

use Alchemy\NotifyBundle\Command\TestNotificationCommand;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use Alchemy\NotifyBundle\Notification\SymfonyNotifier;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AlchemyNotifyBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('novu_dsn')->defaultValue('novu://%env(NOVU_SECRET_KEY)%@%env(NOVU_API_HOST)%')->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $extension = $this->getContainerExtension();
        $configs = $builder->getExtensionConfig($extension->getAlias());
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration($this, $builder, $extension->getAlias()), $configs);

        $builder->prependExtensionConfig('framework', [
            'notifier' => [
                'texter_transports' => [
                    'novu' => $config['novu_dsn'],
                ],
                'channel_policy' => [
                    'high' => 'push',
                ],
            ]
        ]);
    }

    protected function getContainerExtensionClass(): string
    {
        return AlchemyNotifyExtension::class;
    }


    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('%env(NOVU_DSN)%', 'novu://API_KEY@default')
        ;

        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(SymfonyNotifier::class);
        $services->alias(NotifierInterface::class, SymfonyNotifier::class);
        $services->set(TestNotificationCommand::class);
    }
}
