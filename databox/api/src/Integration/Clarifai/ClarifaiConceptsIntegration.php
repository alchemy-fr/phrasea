<?php

declare(strict_types=1);

namespace App\Integration\Clarifai;

use App\Integration\AbstractIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class ClarifaiConceptsIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('apiKey')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
        ;
    }

    public function getWorkflowJobDefinitions(array $config): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            ClarifaiConceptsAction::class,
        );
    }

    public static function getName(): string
    {
        return 'clarify.concepts';
    }

    public static function getTitle(): string
    {
        return 'Clarify concepts';
    }
}
