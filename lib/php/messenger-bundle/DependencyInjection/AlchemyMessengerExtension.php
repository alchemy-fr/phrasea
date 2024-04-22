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

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'routing' => $routing,
            ],
        ]);
    }
}
