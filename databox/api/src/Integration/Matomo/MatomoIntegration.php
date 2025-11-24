<?php

declare(strict_types=1);

namespace App\Integration\Matomo;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class MatomoIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            MatomoAction::class,
        );
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('attribute')
                ->info('Attribute slug where to store Matomo metrics for the asset')
                ->defaultValue('matomoMetrics')
                ->cannotBeEmpty()
            ->end()
        ;
    }

    public static function getTitle(): string
    {
        return 'Matomo';
    }

    public static function getName(): string
    {
        return 'matomo';
    }
}
