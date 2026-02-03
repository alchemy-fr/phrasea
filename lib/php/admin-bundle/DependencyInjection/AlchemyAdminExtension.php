<?php

namespace Alchemy\AdminBundle\DependencyInjection;

use Alchemy\ConfiguratorBundle\StackConfig;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyAdminExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['AlchemyAclBundle'])) {
            $loader->load('acl.yaml');
        }
    }

    private function loadExternalConfig(ContainerBuilder $container, array $serviceConfig): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $siteTitle = $serviceConfig['title'];
        $container->setParameter('alchemy_admin.default_site_title', $siteTitle.' Admin');
        $container->setParameter('alchemy_admin.site_title', StackConfig::generateConfigEnvKey($serviceConfig['name'].'.admin.title', 'alchemy_admin.default_site_title'));
        $container->setParameter('alchemy_admin.logo', StackConfig::generateConfigEnvKey($serviceConfig['name'].'.admin.logo', ''));
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['EasyAdminBundle'])) {
            throw new RuntimeException('You must enable the "EasyAdminBundle"');
        }

        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);
        $this->loadExternalConfig($container, $config['service']);

        $container->setParameter('alchemy_admin.worker_queues', $config['worker']['queues']);
        $container->setParameter('alchemy_admin.worker_rabbitmq', $config['worker']['rabbitmq']);

        $container->prependExtensionConfig('easy_admin', [
            'site_name' => '%alchemy_admin.site_title%',
            'formats' => [
                'date' => 'd/m/Y',
                'time' => 'H:i',
                'datetime' => 'd/m/Y H:i:s',
            ],
            'show' => [
                'max_results' => 100,
            ],
            'user' => [
                'display_name' => true,
                'display_avatar' => false,
                'name_property_path' => 'username',
            ],
        ]);

        if (isset($bundles['TwigBundle'])) {
            $container->prependExtensionConfig('twig', [
                'form_themes' => [
                    'bootstrap_4_layout.html.twig',
                ],
                'globals' => [
                    'dashboard_menu_url' => '%alchemy_admin.dashboard_menu_url%',
                    'services_menu_enabled' => '%alchemy_admin.services_menu_enabled%',
                ],
            ]
            );
        }
    }
}
