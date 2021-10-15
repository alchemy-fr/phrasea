<?php

namespace Alchemy\StorageBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyStorageExtension extends Extension implements PrependExtensionInterface
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
    }

    public function prepend(ContainerBuilder $container)
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
                        'client' => 'alchemy_storage.s3_client.internal',
                        'bucket' => '%env(S3_STORAGE_BUCKET_NAME)%',
                    ]
                ]
            ],
            'filesystems' => [
                'upload' => [
                    'adapter' => 'upload',
                ]
            ]
        ]);

        $container->prependExtensionConfig('framework', [
            'validation' => [
                'enabled' => true,
                'enable_annotations' => false,
                'mapping' => [
                    'paths' => [
                        __DIR__ . '/../Resources/config/validator/validation.yaml',
                    ],
                ],
            ],
        ]);
    }
}
