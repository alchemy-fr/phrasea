<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Subscriber\KeycloakUserDirectory;
use Alchemy\NotifierBundle\Topic\BuiltInTopic;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AlchemyNotifierBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $channelValues = ChannelType::values();

        $definition->rootNode()
            ->children()
                ->scalarNode('enabled')
                    ->info('Globally enable/disable notification delivery (bool or env placeholder)')
                    ->defaultValue('%env(bool:NOTIFICATIONS_ENABLED)%')
                ->end()
                ->scalarNode('template_namespace')
                    ->info('Twig namespace under which the application exposes notification templates')
                    ->defaultValue('@notifications')
                ->end()
                ->scalarNode('client_url')
                    ->info('Base URL of the front client, used to turn notification URIs into absolute links')
                    ->defaultValue('')
                ->end()
                ->scalarNode('notification_uri_path')
                    ->info('Client route intercepting notification links and redirecting to the final destination')
                    ->defaultValue('/notification-uri')
                ->end()
                ->arrayNode('default_channels')
                    ->info('Channels used by a topic when it does not declare its own')
                    ->enumPrototype()->values($channelValues)->end()
                    ->defaultValue([ChannelType::Email->value, ChannelType::InApp->value])
                ->end()
                ->scalarNode('in_app_channel_prefix')
                    ->info('Pusher channel prefix used for in-app notifications (suffixed with the userId)')
                    ->defaultValue('private-user-')
                ->end()
                ->scalarNode('in_app_event')
                    ->info('Pusher event name triggered for in-app notifications')
                    ->defaultValue('notification')
                ->end()
                ->scalarNode('user_directory')
                    ->info('Default audience of a broadcast: "keycloak" (every user of the realm) or "subscribers" (only the users already known locally)')
                    ->defaultValue(KeycloakUserDirectory::NAME)
                ->end()
                ->integerNode('directory_batch_size')
                    ->info('Page size used when listing the users of the identity provider')
                    ->min(1)
                    ->defaultValue(100)
                ->end()
                ->arrayNode('topics')
                    ->info('Notification topics exposed by the application')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('channels')
                                ->info('Channels this topic is delivered through (defaults to default_channels)')
                                ->enumPrototype()->values($channelValues)->end()
                                ->defaultValue([])
                            ->end()
                            ->scalarNode('importance')->defaultValue('normal')->end()
                            ->booleanNode('user_configurable')
                                ->info('Whether users may toggle this topic in their preferences')
                                ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $bundles = $builder->getParameter('kernel.bundles');

        if (isset($bundles['DoctrineBundle'])) {
            $builder->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'AlchemyNotifierBundle' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => __DIR__.'/Entity',
                            'prefix' => 'Alchemy\\NotifierBundle\\Entity',
                            'alias' => 'notifier',
                        ],
                    ],
                ],
            ]);
        }
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('env(NOTIFICATIONS_ENABLED)', false)
            ->set('alchemy_notifier.enabled', $config['enabled'])
            ->set('alchemy_notifier.default_channels', $config['default_channels'])
            ->set('alchemy_notifier.topics', $this->normalizeTopics($config))
            ->set('alchemy_notifier.template_namespace', $config['template_namespace'])
            ->set('alchemy_notifier.client_url', $config['client_url'])
            ->set('alchemy_notifier.notification_uri_path', $config['notification_uri_path'])
            ->set('alchemy_notifier.in_app.channel_prefix', $config['in_app_channel_prefix'])
            ->set('alchemy_notifier.in_app.event', $config['in_app_event'])
            ->set('alchemy_notifier.user_directory', $config['user_directory'])
            ->set('alchemy_notifier.directory_batch_size', $config['directory_batch_size'])
        ;

        $container->import('../config/services.yaml');
    }

    /**
     * @return array<string, array{channels: array<int, string>, importance: string, user_configurable: bool}>
     */
    private function normalizeTopics(array $config): array
    {
        // Built-in topics ship their own templates, so they work out of the box;
        // an application may still redeclare them to change their channels.
        $topics = [
            BuiltInTopic::ADMIN_MESSAGE => [
                'channels' => ChannelType::values(),
                'importance' => 'normal',
                'user_configurable' => true,
            ],
        ];

        foreach ($config['topics'] as $key => $topic) {
            $topics[$key] = [
                'channels' => [] !== $topic['channels'] ? $topic['channels'] : $config['default_channels'],
                'importance' => $topic['importance'],
                'user_configurable' => $topic['user_configurable'],
            ];
        }

        return $topics;
    }
}
