<?php

namespace Alchemy\WorkflowBundle\DependencyInjection;

use Alchemy\Workflow\Doctrine\Entity\JobState;
use Alchemy\Workflow\Doctrine\Entity\WorkflowState;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('alchemy_workflow');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('workflow_state_entity')
                            ->defaultValue(WorkflowState::class)
                        ->end()
                        ->scalarNode('job_state_entity')
                            ->defaultValue(JobState::class)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('workflows_dirs')
                    ->defaultValue(['%kernel.project_dir%/config/workflows'])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
