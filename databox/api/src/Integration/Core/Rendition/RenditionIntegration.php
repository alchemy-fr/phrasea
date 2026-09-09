<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\Config\RenditionConfigNormalizerTrait;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Service\Storage\RenditionManager;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class RenditionIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    use RenditionConfigNormalizerTrait;

    public function __construct(
        private readonly RenditionManager $renditionManager,
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder
            ->arrayNode('renditions')
                ->info('Renditions to build explicitly. If omitted, all renditions are built.')
                ->scalarPrototype()
                ->end()
            ->end()
        ;
        // @formatter:on
    }

    public function validateConfiguration(IntegrationConfig $config): void
    {
        $renditions = $config['renditions'] ?? [];
        foreach ($renditions as $rendition) {
            // Accepts a name or an ID
            $this->renditionManager->getRenditionDefinitionByName($config->getWorkspaceId(), $rendition);
        }
    }

    protected function getRenditionConfigPaths(): array
    {
        return ['renditions'];
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        $filteredRenditions = $config['renditions'] ?? [];
        if (empty($filteredRenditions)) {
            $definitions = $this->renditionManager->getRenditionDefinitions($config->getWorkspaceId());
        } else {
            $definitions = $this->renditionManager->getRenditionDefinitionByIds($config->getWorkspaceId(), $filteredRenditions);
            $definitionsIndex = [];
            foreach ($definitions as $definition) {
                if (isset($definitionsIndex[$definition->getId()])) {
                    continue;
                }
                $definitionsIndex[$definition->getId()] = $definition;
                $parent = $definition;
                while ($parent = $parent->getParent()) {
                    if (isset($definitionsIndex[$parent->getId()])) {
                        break;
                    }
                    $definitionsIndex[$parent->getId()] = $parent;
                }
            }
            $definitions = array_values($definitionsIndex);
        }

        $jobs = [];
        foreach ($definitions as $definition) {
            $j = WorkflowHelper::createIntegrationJob(
                $config,
                RenditionBuildAction::class,
                self::getJobIdSuffix($definition->getId()),
                $definition->getName(),
            );
            $j->getWith()->offsetSet('definition', $definition->getId());
            $jobs[$definition->getId()] = $j;
        }

        foreach ($definitions as $definition) {
            if (null !== $parent = $definition->getParent()) {
                $jobs[$definition->getId()]->getNeeds()->append($jobs[$parent->getId()]->getId());
            }
        }

        return $jobs;
    }

    private static function getJobIdSuffix(string $renditionDefinitionId): string
    {
        return RenditionBuildAction::JOB_ID.':'.$renditionDefinitionId;
    }

    public static function getJobId(IntegrationConfig $config, string $renditionDefinitionId): string
    {
        return sprintf(
            '%s:%s',
            WorkflowHelper::getJobIdPrefix($config),
            self::getJobIdSuffix($renditionDefinitionId),
        );
    }

    public static function getName(): string
    {
        return 'core.rendition';
    }

    public static function getDisplayName(): string
    {
        return 'Rendition';
    }
}
