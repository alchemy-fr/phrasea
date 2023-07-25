<?php

namespace Alchemy\WorkflowBundle\DependencyInjection;

use Alchemy\Workflow\Doctrine\Entity\WorkflowState;
use Alchemy\WorkflowBundle\Doctrine\EntityLoadListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class AlchemyWorkflowExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('workflow.yaml');

        if (WorkflowState::class !== $config['doctrine']['workflow_state_entity']) {
            $def = new Definition(EntityLoadListener::class);
            $def->setArgument('$workflowStateEntity', $config['doctrine']['workflow_state_entity']);
            $def->setArgument('$jobStateEntity', $config['doctrine']['job_state_entity']);
            $container->setDefinition(EntityLoadListener::class, $def);
        }

        $def = $container->getDefinition('alchemy.workflow.state_repository');
        $def->setArgument('$workflowStateEntity', $config['doctrine']['workflow_state_entity']);
        $def->setArgument('$jobStateEntity', $config['doctrine']['job_state_entity']);

        $def = $container->getDefinition('alchemy.workflow.workflow_repository.file');
        $def->setArgument('$dirs', $config['workflows_dirs']);
    }

    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'AlchemyWorkflowBundle' => [
                            'type' => 'yml',
                            'is_bundle' => true,
                            'prefix' => 'Alchemy\\Workflow\\Doctrine\\Entity',
                        ],
                    ],
                ],
            ]);
        }
    }
}
