<?php

namespace Alchemy\RemoteAuthBundle\DependencyInjection;

use Alchemy\RemoteAuthBundle\Client\AdminClient;
use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\RemoteAuthBundle\Listener\LogoutListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyRemoteAuthExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        if ('test' === $container->getParameter('kernel.environment')) {
            $loader->load('services_test.yaml');
        }

        $def = $container->findDefinition(AdminClient::class);
        $def->setArgument('$clientId', $config['admin_auth']['client_id']);
        $def->setArgument('$clientSecret', $config['admin_auth']['client_secret']);

        $def = $container->findDefinition(KeycloakUrlGenerator::class);
        $def->setArgument('$baseUrl', $config['keycloak']['url']);
        $def->setArgument('$realm', $config['keycloak']['realm']);

        $def = $container->findDefinition(LogoutListener::class);
        $def->setArgument('$clientId', $config['admin_auth']['client_id']);

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['AlchemyAclBundle'])) {
            $loader->load('bridge/acl_bundle.yaml');
        }
    }
}
