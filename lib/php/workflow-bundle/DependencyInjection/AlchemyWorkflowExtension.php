<?php

namespace Alchemy\WorkflowBundle\DependencyInjection;

use Alchemy\WorkflowBundle\DependencyInjection\Compiler\HealthCheckerPass;
use Alchemy\WorkflowBundle\Health\Checker\DoctrineConnectionChecker;
use Alchemy\WorkflowBundle\Health\Checker\RabbitMQConnectionChecker;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyWorkflowExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('workflow.yaml');

        $def = $container->getDefinition('alchemy.workflow.workflow_repository.file');
        $def->setArgument('$dirs', $config['workflows_dirs']);
    }

    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'orm' =>[
                    'mappings' => [
                        'AlchemyWorkflowBundle' => [
                            'type' => 'yml',
                            'is_bundle' => true,
                            'prefix' => 'Alchemy\\Workflow\\Doctrine\\Entity',
                        ]
                    ]
                ]
            ]);
        }
    }
}
