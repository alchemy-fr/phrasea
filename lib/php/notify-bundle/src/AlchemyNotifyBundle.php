<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle;

use Alchemy\NotifyBundle\Command\BroadcastNotificationCommand;
use Alchemy\NotifyBundle\Command\TestNotificationCommand;
use Alchemy\NotifyBundle\Controller\NotificationController;
use Alchemy\NotifyBundle\Message\AddTopicSubscribersHandler;
use Alchemy\NotifyBundle\Message\NotifyTopicHandler;
use Alchemy\NotifyBundle\Message\RemoveTopicSubscribersHandler;
use Alchemy\NotifyBundle\Message\UpdateSubscribersHandler;
use Alchemy\NotifyBundle\Notification\MockNotifier;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use Alchemy\NotifyBundle\Notification\SymfonyNotifier;
use Alchemy\NotifyBundle\Service\NovuClient;
use Alchemy\NotifyBundle\Service\NovuManager;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
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
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('framework', [
            'notifier' => [
                'texter_transports' => [
                    'novu' => 'novu://%env(NOVU_SECRET_KEY)%@%env(NOVU_API_HOST)%',
                ],
                'channel_policy' => [
                    'high' => 'push',
                ],
            ],
            'http_client' => [
                'scoped_clients' => [
                    'novu.client' => [
                        'base_uri' => 'https://%env(NOVU_API_HOST)%',
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
            ->set('env(NOTIFICATIONS_ENABLED)', false)
            ->set('env(NOVU_SECRET_KEY)', '')
            ->set('env(NOVU_API_HOST)', 'api.novu.test')
        ;

        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(NovuManager::class);
        $services->set(NovuClient::class);
        $services->set(SymfonyNotifier::class)
            ->arg('$notifyAuthor', $config['notify_author']);
        $services->set(MockNotifier::class);

        $isTest = 'test' === $builder->getParameter('kernel.environment');

        $services->alias(NotifierInterface::class, $config['notifier_service'] ?? ($isTest ? MockNotifier::class : SymfonyNotifier::class));
        $services->set(BroadcastNotificationCommand::class);
        $services->set(TestNotificationCommand::class);
        $services->set(NotificationController::class);
        $services->set(AddTopicSubscribersHandler::class);
        $services->set(RemoveTopicSubscribersHandler::class);
        $services->set(NotifyTopicHandler::class);
        $services->set(UpdateSubscribersHandler::class);
    }
}
