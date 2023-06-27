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

final readonly class IntegrationWorkflowRepository implements WorkflowRepositoryInterface
{
    private const ASSET_INGEST_NAME = 'asset-ingest';
    private const ATTRIBUTES_UPDATE_NAME = 'attributes-update';
    private const ROOT_WORKFLOWS = [
        self::ATTRIBUTES_UPDATE_NAME,
        self::ASSET_INGEST_NAME,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
        private WorkflowRepositoryInterface $decorated,
    ) {
    }

    public function loadWorkflowByName(string $name): ?Workflow
    {
        foreach (self::ROOT_WORKFLOWS as $rootName) {
            $prefix = $rootName.':';
            if (str_starts_with($name, $prefix)) {
                $workspaceId = substr($name, strlen($prefix));

                $workflow = $this->decorated->loadWorkflowByName($rootName);

                return $this->createIntegrationsToWorkflow($workflow, $workspaceId);
            }
        }

        return $this->decorated->loadWorkflowByName($name);
    }

    public function getWorkflowsByEvent(WorkflowEvent $event): array
    {
        $inputs = $event->getInputs();
        $workspaceId = $inputs['workspaceId'] ?? null;

        $workflows = $this->decorated->getWorkflowsByEvent($event);
        if (empty($workspaceId)) {
            return $workflows;
        }

        foreach ($workflows as $key => $workflow) {
            $workflows[$key] = $this->createIntegrationsToWorkflow($workflow, $workspaceId);
        }

        return $workflows;
    }

    private function createIntegrationsToWorkflow(Workflow $workflow, string $workspaceId): Workflow
    {
        if (!in_array($workflow->getName(), self::ROOT_WORKFLOWS, true)) {
            return $workflow;
        }

        $integrationWorkflow = clone $workflow;
        $integrationWorkflow->rename($workflow->getName().':'.$workspaceId);
        $jobList = $integrationWorkflow->getJobs();

        $workspaceIntegrations = $this->em->getRepository(WorkspaceIntegration::class)
            ->findBy([
                'workspace' => $workspaceId,
                'enabled' => true,
            ], [
                'createdAt' => 'ASC',
                'id' => 'ASC',
            ]);

        /* @var array<string, Job[]> $jobMap */
        $jobMap = [];
        $integrationConfigs = [];
        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);

            if ($config['integration'] instanceof WorkflowIntegrationInterface) {
                $integrationConfigs[] = $config;
            }
        }

        foreach ($integrationConfigs as $config) {
            $jobs = [];
            [
                'integration' => $integration,
                'workspaceIntegration' => $workspaceIntegration,
            ] = $config;

            /** @var Job $jobDefinition */
            foreach ($integration->getWorkflowJobDefinitions($config, $workflow) as $jobDefinition) {
                $jobList->offsetSet($jobDefinition->getId(), $jobDefinition);
                $jobDefinition->setContinueOnError(true);
                $jobs[] = $jobDefinition;
            }
            $jobMap[$workspaceIntegration->getId()] = $jobs;
        }

        foreach ($integrationConfigs as $config) {
            $workspaceIntegration = $config['workspaceIntegration'];

            foreach ($workspaceIntegration->getNeeds() as $need) {
                foreach ($jobMap[$workspaceIntegration->getId()] as $job) {
                    $needList = $job->getNeeds();

                    foreach ($jobMap[$workspaceIntegration->getId()] as $j) {
                        if ($needList->has($j->getId())) {
                            continue 2;
                        }
                    }

                    foreach ($jobMap[$need->getId()] as $neededJob) {
                        $needList->append($neededJob->getId());
                    }
                }
            }
        }

        return $integrationWorkflow;
    }

    public function loadAll(): void
    {
    }
}
