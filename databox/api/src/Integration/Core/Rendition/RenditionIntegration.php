<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\Workflow\Model\Workflow;
use App\Entity\Core\RenditionDefinition;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Service\Storage\RenditionManager;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class RenditionIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
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
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        /** @var RenditionDefinition[] $definitions */
        $definitions = $this->renditionManager->getRenditionDefinitions($config->getWorkspaceId());

        $renditions = $config['renditions'] ?? null;

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

        $neededDefinitions = [];
        foreach ($definitions as $definition) {
            if (null !== $parent = $definition->getParent()) {
                $jobs[$definition->getId()]->getNeeds()->append($jobs[$parent->getId()]->getId());
                $neededDefinitions[$parent->getId()] = true;
            }
        }

        if (null !== $renditions) {
            foreach ($definitions as $definition) {
                if (!in_array($definition->getName(), $renditions, true) && !isset($neededDefinitions[$definition->getId()])) {
                    unset($jobs[$definition->getId()]);
                }
            }
        }

        return array_values($jobs);
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
