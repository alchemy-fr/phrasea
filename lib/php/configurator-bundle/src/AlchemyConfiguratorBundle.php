<?php

declare(strict_types=1);

namespace Alchemy\ConfiguratorBundle;

use Alchemy\ConfiguratorBundle\Command\PushConfigToBucketCommand;
use Alchemy\ConfiguratorBundle\Controller\ConfiguratorEntryCrudController;
use Alchemy\ConfiguratorBundle\Documentation\AppConfigDocumentationGenerator;
use Alchemy\ConfiguratorBundle\Documentation\GlobalConfigDocumentationGenerator;
use Alchemy\ConfiguratorBundle\Dumper\JsonDumper;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntryRepository;
use Alchemy\ConfiguratorBundle\Form\Type\ConfigurationKeyType;
use Alchemy\ConfiguratorBundle\Message\DeployConfigHandler;
use Alchemy\ConfiguratorBundle\Pusher\BucketPusher;
use Alchemy\ConfiguratorBundle\Schema\GlobalConfigurationSchema;
use Alchemy\ConfiguratorBundle\Schema\SchemaProviderInterface;
use Alchemy\ConfiguratorBundle\Service\ConfigurationReference;
use Alchemy\ConfiguratorBundle\Validator\ValidConfigurationEntryConstraintValidator;
use Alchemy\CoreBundle\Documentation\DocumentationGeneratorInterface;
use Aws\S3\S3Client;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AlchemyConfiguratorBundle extends AbstractBundle
{
    private const string S3_CLIENT_SERVICE = 'alchemy_configurator.s3_client';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('database_url')->defaultValue('%env(CONFIGURATOR_DATABASE_URL)%')->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('bucket_name')->defaultValue('%env(CONFIGURATOR_S3_BUCKET_NAME)%')->end()
                        ->booleanNode('use_path_style_endpoint')->defaultValue('%env(bool:CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT)%')->end()
                        ->scalarNode('endpoint')->defaultValue('%env(CONFIGURATOR_S3_ENDPOINT)%')->end()
                        ->scalarNode('path_prefix')->defaultValue('%env(CONFIGURATOR_S3_PATH_PREFIX)%')->end()
                        ->scalarNode('access_key')->defaultValue('%env(CONFIGURATOR_S3_ACCESS_KEY)%')->end()
                        ->scalarNode('secret_key')->defaultValue('%env(CONFIGURATOR_S3_SECRET_KEY)%')->end()
                        ->scalarNode('region')->defaultValue('%env(CONFIGURATOR_S3_REGION)%')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
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
                ],
            ],
        ]);

        $builder->prependExtensionConfig('stof_doctrine_extensions', [
            'default_locale' => 'en_US',
            'orm' => [
                'configurator' => [
                    'timestampable' => true,
                ],
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (file_exists(StackConfig::SRC)) {
            $builder->addResource(new FileResource(StackConfig::SRC));
        }

        $container->parameters()
            ->set('env(CONFIGURATOR_DB_NAME)', 'configurator')
            ->set('env(POSTGRES_USER)', 'user')
            ->set('env(POSTGRES_PASSWORD)', 'ChangeMe')
            ->set('env(POSTGRES_HOST)', 'db')
            ->set('env(POSTGRES_PORT)', '5432')
            ->set('env(CONFIGURATOR_DATABASE_URL)', 'pgsql://%env(POSTGRES_USER)%:%env(POSTGRES_PASSWORD)%@%env(POSTGRES_HOST)%:%env(POSTGRES_PORT)%/%env(CONFIGURATOR_DB_NAME)%')
        ;

        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $storage = $config['storage'];
        $s3ClientId = 'alchemy_configurator.s3_client';

        $services->set(ConfiguratorEntryCrudController::class)->public();

        $services->set(ConfiguratorEntryRepository::class);
        $services->set(PushConfigToBucketCommand::class);
        $services->set(ValidConfigurationEntryConstraintValidator::class);
        $services->set(JsonDumper::class);
        $services->set(ConfigurationKeyType::class);
        $services->set(ConfigurationReference::class);
        $services->set(GlobalConfigurationSchema::class)
            ->tag(SchemaProviderInterface::TAG)
        ;
        $services->set(AppConfigDocumentationGenerator::class)
            ->tag(DocumentationGeneratorInterface::TAG);
        $services->set(GlobalConfigDocumentationGenerator::class)
            ->tag(DocumentationGeneratorInterface::TAG);
        $services->set(BucketPusher::class);
        $services->set(Deployer::class);
        $services->set(DeployConfigHandler::class);

        $services->set($s3ClientId, S3Client::class)
            ->arg(0, [
                'version' => 'latest',
                'region' => $storage['region'],
                'use_path_style_endpoint' => $storage['use_path_style_endpoint'],
                'endpoint' => $storage['endpoint'],
                'credentials' => [
                    'key' => $storage['access_key'],
                    'secret' => $storage['secret_key'],
                ],
                'http' => [
                    'verify' => '%env(bool:VERIFY_SSL)%',
                ],
            ]);

        $services->set(BucketPusher::class)
            ->arg('$s3Client', new Reference(self::S3_CLIENT_SERVICE))
            ->arg('$bucketName', $storage['bucket_name'])
            ->arg('$pathPrefix', $storage['path_prefix'])
        ;
    }
}
