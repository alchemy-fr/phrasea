<?php

declare(strict_types=1);

namespace App\Integration\Core\Test;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class TestAssetOperationIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const string VERSION = '1.0';

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('attribute')
                ->defaultValue('test')
                ->cannotBeEmpty()
            ->end()
        ;
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            TestAction::class,
        );
    }

    public static function getName(): string
    {
        return 'test.asset_operation';
    }

    public static function getTitle(): string
    {
        return 'Test asset operation';
    }
}
