<?php

namespace Alchemy\RemoteAuthBundle\DependencyInjection;

use Alchemy\RemoteAuthBundle\Client\AdminClient;
use Alchemy\RemoteAuthBundle\Security\LoginFormAuthenticator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
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
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('app.verify_ssl', 'prod' === $container->getParameter('kernel.environment'));

        if ('test' === $container->getParameter('kernel.environment')) {
            $loader->load('services_test.yaml');
        }

        foreach ($config['login_forms'] as $name => $loginForm) {
            $def = new ChildDefinition(LoginFormAuthenticator::class);
            $def->setArgument('$routeName', $loginForm['route_name']);
            $def->setAbstract(false);
            $def->setPublic(true);
            $def->setArgument('$defaultTargetPath', $loginForm['default_target_path']);
            $container->setDefinition('alchemy_remote.login_form.'.$name, $def);
        }

        $mapperDef = $container->findDefinition(AdminClient::class);
        $mapperDef->setArgument('$clientId', $config['admin_auth']['client_id']);
        $mapperDef->setArgument('$clientSecret', $config['admin_auth']['client_secret']);
    }
}
