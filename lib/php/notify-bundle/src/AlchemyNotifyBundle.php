<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle;

use Alchemy\NotifyBundle\Command\TestNotificationCommand;
use Alchemy\NotifyBundle\Notification\MockNotifier;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use Alchemy\NotifyBundle\Notification\SymfonyNotifier;
use Alchemy\NotifyBundle\Service\NovuClient;
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
                ->scalarNode('notifier_service')->defaultNull()->end()
                ->scalarNode('notify_author')->defaultValue('%env(bool:NOTIFY_AUTHOR)%')->end()
                ->arrayNode('novu')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('secret_key')->defaultValue('%env(NOVU_SECRET_KEY)%')->end()
                        ->scalarNode('api_host')->defaultValue('%env(NOVU_API_HOST)%')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $extension = $this->getContainerExtension();
        $configs = $builder->getExtensionConfig($extension->getAlias());
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration($this, $builder, $extension->getAlias()), $configs);
        $novuConfig = $config['novu'];

        $builder->prependExtensionConfig('framework', [
            'notifier' => [
                'texter_transports' => [
                    'novu' => sprintf('novu://%s@%s', $novuConfig['secret_key'], $novuConfig['api_host']),
                ],
                'channel_policy' => [
                    'high' => 'push',
                ],
            ],
            'http_client' => [
                'scoped_clients' => [
                    'novu.client' => [
                        'base_uri' => sprintf('https://%s', $novuConfig['api_host']),
                        'verify_peer' => '%env(bool:VERIFY_SSL)%',
                    ],
                ],
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('env(NOTIFY_AUTHOR)', false)
            ->set('alchemy_notify.novu.api_host', $config['novu']['api_host'])
            ->set('alchemy_notify.novu.secret_key', $config['novu']['secret_key'])
        ;

        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(NovuClient::class);
        $services->set(SymfonyNotifier::class)
            ->arg('$notifyAuthor', $config['notify_author']);
        $services->set(MockNotifier::class);

        $isTest = 'test' === $builder->getParameter('kernel.environment');

        $services->alias(NotifierInterface::class, $config['notifier_service'] ?? ($isTest ? MockNotifier::class : SymfonyNotifier::class));
        $services->set(TestNotificationCommand::class);
    }
}
