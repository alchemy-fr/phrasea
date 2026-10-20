<?php

declare(strict_types=1);

namespace App\Integration\Core\Similarity;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\Config\RenditionConfigNormalizerTrait;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\FilterNeedIntegrationInterface;
use App\Integration\IntegrationCategory;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Notification\EntityDisableNotifyableException;
use App\Service\Storage\RenditionManager;
use App\Service\Vector\AssetEmbeddingManager;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
class SimilarityIntegration extends AbstractIntegration implements FilterNeedIntegrationInterface, WorkflowIntegrationInterface
{
    use RenditionConfigNormalizerTrait;

    public const string NAME = 'core.similarity';

    final public const string VERSION = '1.0';

    public function __construct(
        private readonly RenditionManager $renditionManager,
        private readonly AssetEmbeddingManager $assetEmbeddingManager,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('rendition')
                ->defaultValue(AssetEmbeddingManager::DEFAULT_RENDITION)
                ->cannotBeEmpty()
                ->info('Rendition used to compute the embedding. When it is not an image (document, video), the thumbnail is used instead.')
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

            // The thumbnail is the fallback of a rendition that is not an image (document, video)
            $definitionIds = array_unique([
                $renditionDefinition->getId(),
                ...$this->assetEmbeddingManager->getFallbackRenditionDefinitionIds($neededIntegrationConfig->getWorkspaceId()),
            ]);

            return array_values(array_map(
                static fn (string $definitionId): string => RenditionIntegration::getJobId($neededIntegrationConfig, $definitionId),
                $definitionIds,
            ));
        }

        return null;
    }

    public static function getDisplayName(): string
    {
        return 'Similarity';
    }

    protected function getRenditionConfigPaths(): array
    {
        return ['rendition'];
    }

    public static function getCategories(): array
    {
        return [
            IntegrationCategory::Ai,
        ];
    }
}
