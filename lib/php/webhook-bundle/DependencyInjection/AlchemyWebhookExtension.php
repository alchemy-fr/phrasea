<?php

namespace Alchemy\WebhookBundle\DependencyInjection;

use Alchemy\WebhookBundle\Doctrine\Listener\EntityListener;
use Alchemy\WebhookBundle\Webhook\ObjectNormalizer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Parser;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyWebhookExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('alchemy_webhook.events', $this->buildEvents($config));
        $container->setParameter('alchemy_webhook.listener_config', $config['entities']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        if (isset($config['normalizer_roles'])) {
            $def = $container->getDefinition(ObjectNormalizer::class);
            $def->setArgument('$normalizerRoles', $config['normalizer_roles']);
        }
    }

    private function buildEvents(array $config): array
    {
        $events = $config['events'];
        $eventTypes = [
            EntityListener::EVENT_CREATE => 'When resource <b>%s</b> is created',
            EntityListener::EVENT_UPDATE => 'When resource <b>%s</b> is updated',
            EntityListener::EVENT_DELETE => 'When resource <b>%s</b> is deleted',
        ];

        foreach ($config['entities'] as $crud) {
            foreach ($eventTypes as $e => $desc) {
                $eventName = sprintf('%s:%s', $crud['name'], $e);
                $events[$eventName] = [
                    'description' => sprintf($desc, $crud['name']),
                ];
            }
        }

        return $events;
    }

    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['EasyAdminBundle'])) {
            $data = (new Parser())->parse(file_get_contents(__DIR__.'/../Resources/config/easy_admin_entities.yaml'));
            $container->prependExtensionConfig('easy_admin', $data['easy_admin']);
        }
        if (isset($bundles['TwigBundle'])) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    '%kernel.project_dir%/vendor/alchemy/webhook-bundle/Resources' => 'AlchemyWebhookBundle',
                ],
                'form_themes' => [
                    '@AlchemyWebhookBundle/views/form.html.twig'
                ]
            ]);
        }
    }
}
