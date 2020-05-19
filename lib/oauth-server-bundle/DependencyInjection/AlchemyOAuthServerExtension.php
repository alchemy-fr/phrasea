<?php

namespace Alchemy\OAuthServerBundle\DependencyInjection;

use Alchemy\OAuthServerBundle\Doctrine\Listener\MetadataListener;
use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Entity\AuthCode;
use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use Alchemy\OAuthServerBundle\Entity\RefreshToken;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyOAuthServerExtension extends Extension implements PrependExtensionInterface
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
        if (class_exists(AbstractType::class)) {
            $loader->load('forms.yaml');
        }

        $container->setParameter('alchemy_oauth_server.allowed_scopes', $config['scopes']);

        if ($config['user']['enabled']) {
            $definition = new Definition(MetadataListener::class, [
                $config['user']['class'],
            ]);
            $definition->addTag('doctrine.event_subscriber');
            $container->setDefinition(MetadataListener::class, $definition);
        }
    }

    public function getAlias()
    {
        return 'alchemy_oauth_server';
    }

    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['FOSOAuthServerBundle'])) {
            throw new RuntimeException('You must enable the "FOSOAuthServerBundle"');
        }

        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->prependExtensionConfig('fos_oauth_server', [
            'db_driver' => 'orm',
            'client_class' => OAuthClient::class,
            'access_token_class' => AccessToken::class,
            'refresh_token_class' => RefreshToken::class,
            'auth_code_class' => AuthCode::class,
            'service' => [
                'user_provider' => 'App\User\UserManager',
                'options' => [
                    'access_token_lifetime' => 7776000,
                    'supported_scopes' => implode(' ', $config['scopes']),
                ],
            ],
        ]);

        $container->prependExtensionConfig('framework', [
            'templating' => [
                'engine' => 'twig',
            ],
        ]);

        if (isset($bundles['EasyAdminBundle'])) {
            $data = (new YamlParser())->parse(file_get_contents(__DIR__.'/../Resources/config/easy_admin_entities.yaml'));
            $container->prependExtensionConfig('easy_admin', $data['easy_admin']);
        }
    }
}
