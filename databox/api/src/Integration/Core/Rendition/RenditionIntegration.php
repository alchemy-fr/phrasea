<?php

declare(strict_types=1);

namespace App\Integration\Core\Rendition;

use Alchemy\Workflow\Model\Workflow;
use App\Entity\Core\RenditionDefinition;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Storage\RenditionManager;

class RenditionIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function __construct(
        private readonly RenditionManager $renditionManager,
    ) {
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        /** @var RenditionDefinition[] $definitions */
        $definitions = $this->renditionManager->getRenditionDefinitions($config->getWorkspaceId());

        $jobs = [];

        foreach ($definitions as $definition) {
            $jobs[$definition->getId()] = WorkflowHelper::createIntegrationJob(
                $config,
                RenditionBuildAction::class,
                RenditionBuildAction::JOB_ID.':'.$definition->getId(),
                $definition->getName(),
            );
        }

        foreach ($definitions as $definition) {
            if (null !== $parent = $definition->getParent()) {
                $jobs[$definition->getId()]->getNeeds()->append($jobs[$parent->getId()]->getId());
            }
        }

        return $jobs;
    }

    public static function getName(): string
    {
        return 'core.rendition';
    }

    public static function getTitle(): string
    {
        return 'Rendition';
    }
}
