<?php

namespace Alchemy\AuthBundle\DependencyInjection;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\AuthBundle\Listener\LogoutListener;
use Alchemy\AuthBundle\Security\JwtUserProvider;
use Alchemy\AuthBundle\Security\OAuthAuthorizationAuthenticator;
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
class AlchemyAuthExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        if ('test' === $container->getParameter('kernel.environment')) {
            $loader->load('services_test.yaml');
        }

        $def = $container->findDefinition(KeycloakClient::class);
        $def->setArgument('$clientId', $config['client_id']);
        $def->setArgument('$clientSecret', $config['client_secret']);

        $def = $container->findDefinition(KeycloakUrlGenerator::class);
        $def->setArgument('$baseUrl', $config['keycloak']['url']);
        $def->setArgument('$realm', $config['keycloak']['realm']);

        $def = $container->findDefinition(LogoutListener::class);
        $def->setArgument('$clientId', $config['client_id']);

        $def = $container->findDefinition(OAuthAuthorizationAuthenticator::class);
        $def->setArgument('$clientId', $config['client_id']);

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['AlchemyAclBundle'])) {
            $loader->load('bridge/acl_bundle.yaml');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        $container->prependExtensionConfig('framework', [
            'http_client' => [
                'scoped_clients' => [
                    'keycloak.client' => [
                        'base_uri' => '%env(KEYCLOAK_URL)%',
                        'verify_peer' => '%env(bool:VERIFY_SSL)%',
                    ],
                ],
            ],
            'cache' => [
                'pools' => [
                    'keycloak_realm.cache' => [
                        'adapter' => 'cache.adapter.redis',
                        'provider' => '%env(REDIS_URL)%',
                        'default_lifetime' => 60,
                    ],
                    'one_time_token.cache' => [
                        'adapter' => 'cache.adapter.redis',
                        'provider' => '%env(REDIS_URL)%',
                        'default_lifetime' => 5*60,
                    ],
                ],
            ],
        ]);

        $container->prependExtensionConfig('security', [
            'providers' => [
                'jwt_users' => [
                    'id' => JwtUserProvider::class,
                ],
            ],
        ]);

        if (isset($bundles['ApiPlatformBundle'])) {
            $container->prependExtensionConfig('api_platform', [
                'mapping' => [
                    'paths' => [
                        __DIR__.'/../Api/Resource',
                    ]
                ]
            ]);
        }
    }
}
