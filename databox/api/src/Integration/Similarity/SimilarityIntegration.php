<?php

declare(strict_types=1);

namespace App\Integration\Similarity;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\FilterNeedIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Notification\EntityDisableNotifyableException;
use App\Service\Storage\RenditionManager;
use App\Service\Vector\AssetEmbeddingManager;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class SimilarityIntegration extends AbstractIntegration implements FilterNeedIntegrationInterface, WorkflowIntegrationInterface
{
    final public const string VERSION = '1.0';

    public function __construct(
        private readonly RenditionManager $renditionManager,
    ) {
    }

    public static function getName(): string
    {
        return 'similarity';
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('rendition')
                ->defaultValue(AssetEmbeddingManager::DEFAULT_RENDITION)
                ->cannotBeEmpty()
                ->info('Rendition used to compute the embedding (must be an image)')
            ->end()
        ;
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        if (!$workflow->getOn()->hasEventName(AssetIngestWorkflowEvent::EVENT)) {
            return [];
        }

        yield WorkflowHelper::createIntegrationJob(
            $config,
            SimilarityEmbedAction::class,
        );
    }

    public function getNeededJobs(IntegrationConfig $config, IntegrationConfig $neededIntegrationConfig, Job $job): ?array
    {
        $rendition = $config['rendition'] ?? null;
        if (!$rendition) {
            return null;
        }

        if ($neededIntegrationConfig->getIntegration() instanceof RenditionIntegration) {
            try {
                $renditionDefinition = $this->renditionManager
                    ->getRenditionDefinitionByName($neededIntegrationConfig->getWorkspaceId(), $rendition);
            } catch (\InvalidArgumentException $e) {
                throw new EntityDisableNotifyableException($config->getWorkspaceIntegration(), sprintf('Rendition "%s" not found', $rendition), sprintf('Rendition "%s" not found in workspace "%s"', $rendition, $neededIntegrationConfig->getWorkspaceIntegration()->getWorkspace()->getName()), $e->getCode(), $e);
            }

            return [
                RenditionIntegration::getJobId(
                    $neededIntegrationConfig,
                    $renditionDefinition->getId(),
                ),
            ];
        }

        return null;
    }

    public static function getDisplayName(): string
    {
        return 'Similarity';
    }
}
