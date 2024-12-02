<?php

namespace Alchemy\CoreBundle\DependencyInjection;

use Alchemy\CoreBundle\Health\Checker\DoctrineConnectionChecker;
use Alchemy\CoreBundle\Health\HealthCheckerInterface;
use Alchemy\CoreBundle\Pusher\PusherFactory;
use Alchemy\CoreBundle\Pusher\PusherManager;
use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use Monolog\Processor\PsrLogMessageProcessor;
use Pusher\Pusher;
use Ramsey\Uuid\Doctrine\UuidType;
use Sentry\Monolog\Handler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Serializer\Exception\UnsupportedFormatException;
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

        $container->setParameter('alchemy_core.app_id', $config['app_id']);
        $container->setParameter('alchemy_core.app_name', $config['app_name']);

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

        if ($config['pusher']['enabled']) {
            $loader->load('pusher.yaml');
            $def = $container->getDefinition(PusherManager::class);
            $def->setArgument('$disabled', $config['pusher']['disabled']);

            $this->loadPusher($container, $config['pusher']);
        }

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['SentryBundle'])) {
            $loader->load('sentry.yaml');
            $this->loadSentry($container);
        }
    }

    private function loadPusher(ContainerBuilder $container, array $config): void
    {
        if (!class_exists(Pusher::class)) {
            throw new InvalidArgumentException('Missing "pusher/pusher-php-server" dependency. Please run "composer require pusher/pusher-php-server"');
        }

        $def = new Definition(Pusher::class, [
            '$host' => $config['host'],
            '$key' => $config['key'],
            '$secret' => $config['secret'],
            '$appId' => $config['appId'],
            '$verifySsl' => $config['verifySsl'],
        ]);
        $def->setFactory([PusherFactory::class, 'create']);
        $container->setDefinition(Pusher::class, $def);
    }

    private function loadHealthCheckers(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['DoctrineBundle'])) {
            $container->removeDefinition(DoctrineConnectionChecker::class);
        }

        $container->registerForAutoconfiguration(HealthCheckerInterface::class)
            ->addTag(HealthCheckerInterface::TAG);
    }

    private function loadSentry(ContainerBuilder $container): void
    {
        $def = new Definition(PsrLogMessageProcessor::class);
        $def->addTag('monolog.processor', [
            'handler' => 'sentry',
        ]);
        $container->setDefinition(PsrLogMessageProcessor::class, $def);
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
                ],
                'notifier' => [
                    'texter_transports' => [
                        'novu' => 'novu://%env(NOVU_SECRET_KEY)%@%env(NOVU_API_URL)%',
                    ],
                ]
            ]);
        }

        if (isset($bundles['SentryBundle'])) {
            $sentryConfig = [
                'tracing' => [
                    'dbal' => [
                        'enabled' => false,
                    ],
                ],
                'messenger' => [
                    'capture_soft_fails' => false,
                ],
                'options' => [
                    'environment' => '%env(SENTRY_ENVIRONMENT)%',
                    'release' => '%env(SENTRY_RELEASE)%',
                    'send_default_pii' => true,
                    'tags' => [
                        'app.name' => '%alchemy_core.app_name%',
                        'app.id' => '%alchemy_core.app_id%',
                    ],
                    'ignore_exceptions' => [
                        TooManyRequestsHttpException::class,
                        NotFoundHttpException::class,
                        AccessDeniedHttpException::class,
                        UnsupportedFormatException::class,
                        ValidationException::class,
                        UnauthorizedHttpException::class,
                        AccessDeniedException::class,
                        HttpException::class,
                        MethodNotAllowedHttpException::class,
                        NotAcceptableHttpException::class,
                    ],
                ],
            ];

            if ($env !== 'prod') {
                $sentryConfig['dsn'] = null;
            }
            $container->prependExtensionConfig('sentry', $sentryConfig);

            if (isset($bundles['MonologBundle'])) {
                $container->prependExtensionConfig('sentry', [
                    'register_error_handler' => false, // Disables the ErrorListener to avoid duplicated log in sentry
                    'register_error_listener' => false, // Disables the ErrorListener, ExceptionListener and FatalErrorListener integrations of the base PHP SDK
                ]);

                $container->prependExtensionConfig('monolog', [
                    'handlers' => [
                        'sentry' => [
                            'type' => 'service',
                            'id' => Handler::class,
                        ],
                    ],
                ]);
            }
        }

        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => [
                        'uuid' => UuidType::class,
                    ],
                ],
            ]);
        }
    }
}
