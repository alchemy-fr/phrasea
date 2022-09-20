<?php

namespace Alchemy\AdminBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
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
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
    }

    private function loadExternalConfig(ContainerBuilder $container, array $serviceConfig): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $jsonConfigSrc = '/configs/config.json';
        if (file_exists($jsonConfigSrc)) {
            $rootConfig = json_decode(file_get_contents($jsonConfigSrc), true);
            // Add for fresh cache
            $container->addResource(new FileResource($jsonConfigSrc));
        } else {
            $rootConfig = [];
        }

        $serviceName = $serviceConfig['name'];
        $config = $rootConfig[$serviceName] ?? [];

        if (isset($config['admin']['logo']['src'])) {
            $siteLogo = sprintf(
                '<img src="%s" width="%s" title="%s" alt="%s" />',
                $config['admin']['logo']['src'],
                $config['admin']['logo']['with'],
                $serviceConfig['title'],
                $serviceConfig['title']
            );
        } else {
            $siteLogo = null;
        }

        $siteTitle = $serviceConfig['title'];
        $container->setParameter('alchemy_admin.site_logo', $siteLogo);
        $container->setParameter('alchemy_admin.site_title', $siteTitle);
        if ($siteLogo) {
            $adminSiteTitle = sprintf('<div>%s<div>%s</div></div>',
                $siteLogo,
                $siteTitle
            );
        } else {
            $adminSiteTitle = $siteTitle.' Admin';
        }
        $container->setParameter('easy_admin.site_title', $adminSiteTitle);
    }

    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['EasyAdminBundle'])) {
            throw new RuntimeException('You must enable the "EasyAdminBundle"');
        }

        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);
        $this->loadExternalConfig($container, $config['service']);

        $container->prependExtensionConfig('easy_admin', [
                'site_name' => '%easy_admin.site_title%',
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
                'design' => [
                    'templates' => [
                        'layout' => '@AlchemyAdmin/layout.html.twig',
                        'list' => '@AlchemyAdmin/list.html.twig',
                    ],
                ],
            ]
        );

        if (isset($bundles['AlchemyRemoteAuthBundle'])) {
            $container->prependExtensionConfig('alchemy_remote_auth', [
                    'login_forms' => [
                        'admin' => [
                            'route_name' => 'alchemy_admin_login',
                            'default_target_path' => '/admin',
                        ],
                    ],
                ]
            );
        }

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
