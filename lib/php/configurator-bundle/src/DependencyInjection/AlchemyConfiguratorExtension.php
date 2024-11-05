<?php

namespace Alchemy\ConfiguratorBundle\DependencyInjection;

use Alchemy\ConfiguratorBundle\MetadataManipulator;
use Alchemy\ConfiguratorBundle\Pusher\BucketPusher;
use Aws\S3\S3Client;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyConfiguratorExtension extends Extension implements PrependExtensionInterface
{
    private const string S3_CLIENT_SERVICE = 'alchemy_configurator.s3_client';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $storage = $config['storage'];
        $def = new Definition(S3Client::class, [
            '$args' => [
                'version' => 'latest',
                'region'  => $storage['region'],
                'use_path_style_endpoint' => $storage['use_path_style_endpoint'],
                'endpoint' => $storage['endpoint'],
                'credentials' => [
                    'key'    => $storage['access_key'],
                    'secret' => $storage['secret_key']
                ],
                'http' => [
                    'verify' => '%env(bool:VERIFY_SSL)%'
                ],
            ]
        ]);
        $container->setDefinition(self::S3_CLIENT_SERVICE, $def);

        $container->getDefinition(BucketPusher::class)
            ->setArgument('$s3Client', new Reference(self::S3_CLIENT_SERVICE))
            ->setArgument('$bucketName', $storage['bucket_name'])
        ;
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'connections' => [
                    'configurator' => [
                        'url' => '%env(resolve:CONFIGURATOR_DATABASE_URL)%',
                        'driver' => 'pdo_pgsql',
                        'server_version' => '11.2',
                        'charset' => 'utf8',
                    ],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'configurator' => [
                        'connection' => 'configurator',
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'mappings' => [
                            'AlchemyConfiguratorBundle' => [
                                'type' => 'attribute',
                                'is_bundle' => true,
                                'prefix' => 'Alchemy\ConfiguratorBundle\Entity',
                            ],
                        ],
                    ],
                ]
            ],
        ]);

        $container->prependExtensionConfig('stof_doctrine_extensions', [
            'default_locale' => 'en_US',
            'orm' => [
                'configurator' => [
                    'timestampable' => true,
                ],
            ],
        ]);
    }
}
