<?php

declare(strict_types=1);

namespace App\Integration\N8n;

use App\Integration\AbstractIntegration;
use App\Integration\Core\Test\TestAction;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class N8nTriggerIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const VERSION = '1.0';

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('url')
                ->defaultValue('https://n8n.phrasea.local')
                ->cannotBeEmpty()
            ->end()
        ;
    }

    public function getWorkflowJobDefinitions(array $config): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            TestAction::class,
        );
    }

    public static function getName(): string
    {
        return 'n8n.trigger';
    }

    public static function getTitle(): string
    {
        return 'N8N Trigger';
    }
}
