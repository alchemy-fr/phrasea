<?php

namespace Alchemy\StorageBundle\DependencyInjection;

use Alchemy\StorageBundle\Upload\MultipartUploadGuard;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyStorageExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('cdn.yaml');

        $container->setParameter('alchemy_storage.upload.allowed_types', $config['upload']['allowed_types']);
        $container->getDefinition(MultipartUploadPlanner::class)
            ->setArgument('$maxFileSize', $config['upload']['max_file_size']);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        $bundle = 'OneupFlysystemBundle';
        if (!isset($bundles[$bundle])) {
            throw new \LogicException(sprintf('You must enable %s', $bundle));
        }

        $container->prependExtensionConfig('oneup_flysystem', [
            'adapters' => [
                'upload' => [
                    'awss3v3' => [
                        'client' => 'alchemy_storage.s3_client',
                        'bucket' => '%env(S3_BUCKET_NAME)%',
                        'prefix' => '%env(S3_PATH_PREFIX)%',
                    ],
                ],
            ],
            'filesystems' => [
                'upload' => [
                    'adapter' => 'upload',
                ],
            ],
        ]);

        // Defaults of the multipart upload limits (see doc/tech/Storage/01_s3.md), known before the framework configuration is processed
        foreach ([
            'env(MULTIPART_UPLOAD_RATE_LIMIT)' => '60',
            'env(MULTIPART_UPLOAD_MAX_PENDING)' => '20',
            'env(MAX_UPLOAD_FILE_SIZE)' => '107374182400',
        ] as $name => $default) {
            if (!$container->hasParameter($name)) {
                $container->setParameter($name, $default);
            }
        }

        $container->prependExtensionConfig('framework', [
            'rate_limiter' => [
                // By user (see MultipartUploadGuard); an application may set its own "cache_pool" (shared between its replicas)
                MultipartUploadGuard::RATE_LIMITER => [
                    'policy' => 'sliding_window',
                    'limit' => '%env(int:MULTIPART_UPLOAD_RATE_LIMIT)%',
                    'interval' => '1 minute',
                ],
            ],
            'validation' => [
                'enabled' => true,
            ],
        ]);
    }
}
