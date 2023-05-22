<?php

declare(strict_types=1);

namespace App\Workflow;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use App\Integration\WorkflowIntegrationInterface;
use Doctrine\ORM\EntityManagerInterface;

final class IntegrationWorkflowRepository implements WorkflowRepositoryInterface
{
    private const ASSET_INGEST_NAME = 'asset-ingest';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly WorkflowRepositoryInterface $decorated,
    ) {
    }

    public function loadWorkflowByName(string $name): ?Workflow
    {
        $prefix = self::ASSET_INGEST_NAME.':';
        if (!str_starts_with($name, $prefix)) {
            return $this->decorated->loadWorkflowByName($name);
        }

        $workspaceId = substr($name, strlen($prefix));

        $workflow = $this->decorated->loadWorkflowByName(self::ASSET_INGEST_NAME);

        return $this->createIntegrationsToWorkflow($workflow, $workspaceId);
    }

    private function createIntegrationsToWorkflow(Workflow $workflow, string $workspaceId): Workflow
    {
        $integrationWorkflow = clone $workflow;
        $integrationWorkflow->rename($workflow->getName().':'.$workspaceId);
        $jobList = $integrationWorkflow->getJobs();

        $workspaceIntegrations = $this->em->getRepository(WorkspaceIntegration::class)
            ->findBy([
                'workspace' => $workspaceId,
                'enabled' => true,
            ]);

        /** @var Job[] $jobMap */
        $jobMap = [];
        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            $integration = $config['integration'];

            if ($integration instanceof WorkflowIntegrationInterface) {
                $jobs = [];
                foreach ($integration->getWorkflowJobDefinitions($config) as $jobDefinition) {
                    $jobList->offsetSet($jobDefinition->getId(), $jobDefinition);
                    $jobs[] = $jobDefinition;
                }
                $jobMap[$workspaceIntegration->getId()] = $jobs;
            }
        }

        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            $integration = $config['integration'];

            if ($integration instanceof WorkflowIntegrationInterface) {
                foreach ($workspaceIntegration->getNeeds() as $need) {
                    foreach ($jobMap[$workspaceIntegration->getId()] as $job) {
                        $needList = $job->getNeeds();
                        foreach ($jobMap[$need->getId()] as $neededJob) {
                            $needList->append($neededJob->getId());
                        }
                    }
                }
            }
        }

        return $integrationWorkflow;
    }

    public function getWorkflowsByEvent(WorkflowEvent $event): array
    {
        $inputs = $event->getInputs();

        $workspaceId = $inputs['workspaceId'] ?? null;
        if (empty($workspaceId)) {
            return $this->decorated->getWorkflowsByEvent($event);
        }

        $workflows = $this->decorated->getWorkflowsByEvent($event);

        foreach ($workflows as $key => $workflow) {
            if (self::ASSET_INGEST_NAME === $workflow->getName()) {
                $workflows[$key] = $this->createIntegrationsToWorkflow($workflow, $workspaceId);
            }
        }

        return $workflows;
    }

    public function loadAll(): void
    {
    }
}
