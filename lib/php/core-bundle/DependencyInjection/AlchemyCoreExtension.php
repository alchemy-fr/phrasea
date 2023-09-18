<?php

namespace Alchemy\CoreBundle\DependencyInjection;

use Alchemy\CoreBundle\Health\Checker\DoctrineConnectionChecker;
use Alchemy\CoreBundle\Health\Checker\RabbitMQConnectionChecker;
use Monolog\Processor\PsrLogMessageProcessor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Yaml\Yaml;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyCoreExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.yaml');
        $loader->load('redis.yaml');
        $this->loadFixtures($container, $loader);

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['MonologBundle'])) {
            $loader->load('monolog.yaml');
        }

        if (!empty($config['app_url'])) {
            $container->setParameter('alchemy_core.app_url', $config['app_url']);
            $loader->load('router_listener.yaml');
        }

        if ($config['healthcheck']['enabled']) {
            $loader->load('healthcheck.yaml');
            $this->loadHealthCheckers($container);
        }

        if ($config['sentry']['enabled']) {
            $loader->load('sentry.yaml');
            $this->loadSentry($container);
        }
    }

    private function loadHealthCheckers(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['DoctrineBundle'])) {
            $container->removeDefinition(DoctrineConnectionChecker::class);
        }

        if (!isset($bundles['OldSoundRabbitMqBundle'])) {
            $container->removeDefinition(RabbitMQConnectionChecker::class);
        }
    }

    private function loadSentry(ContainerBuilder $container): void
    {
        $env = $container->getParameter('kernel.environment');
        if ('prod' === $env) {
            $def = new Definition(PsrLogMessageProcessor::class);
            $def->addTag('monolog.processor', [
                'handler' =>'sentry',
            ]);
            $container->setDefinition(PsrLogMessageProcessor::class, $def);
        }
    }

    private function loadFixtures(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['AlchemyStorageBundle'], $bundles['HautelookAliceBundle'])) {
            $loader->load('fixtures.yaml');
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        $env = $container->getParameter('kernel.environment');

        if (isset($bundles['MonologBundle'])) {
            $configFile = sprintf(
                '%s/monolog/%s.yaml',
                __DIR__.'/../Resources/config',
                $env
            );

            if (file_exists($configFile)) {
                $container->prependExtensionConfig('monolog', Yaml::parseFile($configFile)['monolog']);
            }
        }
        if (isset($bundles['FrameworkBundle'])) {
            $container->prependExtensionConfig('framework', [
                'http_method_override' => false,
                'session' => [
                    'handler_id' => RedisSessionHandler::class,
                ]
            ]);
        }
        if (isset($bundles['SentryBundle']) && 'prod' === $env) {
            $container->prependExtensionConfig('sentry', [
                'dsn' => '%env(SENTRY_DSN)%',
                'register_error_listener' => false, // Disables the ErrorListener to avoid duplicated log in sentry
                'tracing' => [
                    'dbal' => [
                        'enabled' => false,
                    ],
                ],
                'options' => [
                    'environment' => '%env(SENTRY_ENVIRONMENT)%',
                    'release' => '%env(SENTRY_RELEASE)%',
                    'send_default_pii' => true,
                    'ignore_exceptions' => [
                        TooManyRequestsHttpException::class,
                    ],
                ]
            ]);
        }
    }
}
