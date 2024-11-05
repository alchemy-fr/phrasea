<?php

namespace Alchemy\MessengerBundle\DependencyInjection;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyMessengerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.yaml');

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['SentryBundle'])) {
            $loader->load('sentry.yaml');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'AlchemyMessengerBundle' => [
                            'type' => 'attribute',
                            'is_bundle' => true,
                            'prefix' => 'Alchemy\\MessengerBundle\\Entity',
                            'alias' => 'messenger',
                        ],
                    ],
                ],
            ]);
        }

        $this->buildMessengerMessages($container);
    }

    private function buildMessengerMessages(ContainerBuilder $container): void
    {
        $routing = [];
        $finder = new Finder();
        $baseDir = $container->getParameter('kernel.project_dir').'/src';
        $baseDirLen = strlen($baseDir);
        $finder->files()->name('*.php')->in($baseDir);
        foreach ($finder as $file) {
            $path = substr($file->getPath(), $baseDirLen);
            $className = 'App'.str_replace(DIRECTORY_SEPARATOR, '\\', $path).'\\'.$file->getFilenameWithoutExtension();

            $ref = new \ReflectionClass($className);
            foreach ($ref->getAttributes() as $attribute) {
                $attr = $attribute->newInstance();
                if ($attr instanceof MessengerMessage) {
                    $routing[$className] = $attr->getQueue();
                }
            }
        }

        $isSsl = in_array(strtolower(getenv('RABBITMQ_SSL') ?: ''), [
            '1', 'y', 'true', 'on',
        ], true);

        $container->setParameter('alchemy_messenger.amqp_transport_dsn', 'amqp'.($isSsl ? 's':  '').'://%env(RABBITMQ_USER)%:%env(RABBITMQ_PASSWORD)%@%env(RABBITMQ_HOST)%:%env(RABBITMQ_PORT)%/%env(RABBITMQ_VHOST)%');
        $container->setParameter('alchemy_messenger.amqp_transport_options', [
            'confirm_timeout' => 3,
            'read_timeout' => 3,
            'write_timeout' => 3,
            'heartbeat' => 0,
        ]);

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'serializer' => [
                    'default_serializer' => 'messenger.transport.symfony_serializer',
                    'symfony_serializer' => [
                        'format' => 'json',
                        'context' => []
                    ]
                ],
                'failure_transport' => 'failed',
                'transports' => [
                    'failed' => 'doctrine://default?queue_name=failed',
                    'sync' => 'sync://'
                ],
                'buses' => [
                    'command_bus' => [
                        'middleware' => [
                            'doctrine_ping_connection'
                        ]
                    ]
                ],
                'routing' => $routing,
            ],
        ]);
    }
}
