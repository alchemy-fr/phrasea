<?php

declare(strict_types=1);

namespace App\Integration\Core\Webhook;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class WebhookIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const VERSION = '1.0';

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('method')
                ->defaultValue('POST')
                ->cannotBeEmpty()
            ->end()
            ->scalarNode('url')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('options')
            ->end()
            ->booleanNode('includeInputs')
                ->defaultTrue()
            ->end()
            ->booleanNode('includeOrigin')
                ->defaultTrue()
            ->end()
        ;
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            WebhookAction::class,
        );
    }

    public static function getName(): string
    {
        return 'core.webhook';
    }

    public static function getTitle(): string
    {
        return 'Webhook';
    }
}
