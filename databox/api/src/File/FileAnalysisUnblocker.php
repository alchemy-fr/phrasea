<?php

declare(strict_types=1);

namespace App\File;

use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Workflow\WorkflowState;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Integration\IntegrationManager;
use App\Integration\WorkflowHelper;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\FileRepository;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Safety net for file analyses that never complete.
 *
 * The analysis of a source file runs as a job of the asset ingest workflow. When
 * that job is lost (worker killed, message dropped, exception before the result
 * is written, dependency failed...), the file stays "pending" forever and, when
 * the workspace enforces the analysis, the asset cannot be displayed.
 *
 * This service, meant to run periodically (see app:file:unblock-analyses),
 * inspects the latest ingest workflow of each affected asset and re-triggers
 * the smallest thing that gets the analysis running again.
 */
final readonly class FileAnalysisUnblocker
{
    final public const int DEFAULT_LIMIT = 500;

    public function __construct(
        private EntityManagerInterface $em,
        private FileRepository $fileRepository,
        private AssetRepository $assetRepository,
        private StateRepositoryInterface $stateRepository,
        private WorkflowRepositoryInterface $workflowRepository,
        private WorkflowOrchestrator $workflowOrchestrator,
        private IntegrationManager $integrationManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param \DateTimeImmutable $before Only files created, and workflows/jobs started, before this date are considered stuck
     *
     * @return FileAnalysisUnblockResult[]
     */
    public function unblock(\DateTimeImmutable $before, int $limit = self::DEFAULT_LIMIT, bool $dryRun = false): array
    {
        $results = [];
        $files = $this->fileRepository->findPendingAnalysisSourceFiles($before, FileAnalyzerIntegration::getName(), $limit);

        foreach ($files as $file) {
            foreach ($this->assetRepository->findBySourceFileIds([$file->getId()]) as $asset) {
                if ($asset->isDeleted()) {
                    continue;
                }

                $result = $this->unblockAsset($file, $asset, $before, $dryRun);
                $results[] = $result;

                if (FileAnalysisUnblockAction::Skipped !== $result->action) {
                    $this->logger->info('Unblocked stuck file analysis', [
                        'fileId' => $result->fileId,
                        'assetId' => $result->assetId,
                        'workflowId' => $result->workflowId,
                        'action' => $result->action->value,
                        'reason' => $result->reason,
                        'dryRun' => $dryRun,
                    ]);
                }
            }
        }

        return $results;
    }

    private function unblockAsset(File $file, Asset $asset, \DateTimeImmutable $before, bool $dryRun): FileAnalysisUnblockResult
    {
        $workflow = $this->findLatestIngestWorkflow($asset);

        if (null === $workflow) {
            if (!$dryRun) {
                $this->dispatchIngest($asset);
            }

            return $this->result($file, $asset, FileAnalysisUnblockAction::IngestDispatched, 'no ingest workflow found for the asset');
        }

        $workflowId = $workflow->getId();

        if ($workflow->getStartedAt() >= $before) {
            return $this->result($file, $asset, FileAnalysisUnblockAction::Skipped, 'ingest workflow started recently', $workflowId);
        }

        if (ModelWorkflowState::STATUS_CANCELLED === $workflow->getStatus()) {
            return $this->result($file, $asset, FileAnalysisUnblockAction::Skipped, 'ingest workflow was cancelled', $workflowId);
        }

        $analyzerJobIds = $this->getAnalyzerJobIds($asset);
        $jobsToRerun = [];
        $analyzerTriggered = false;
        $analyzerInProgress = false;
        foreach ($analyzerJobIds as $jobId) {
            $jobState = $this->stateRepository->getLastJobState($workflowId, $jobId);
            if (null === $jobState) {
                continue;
            }
            $analyzerTriggered = true;

            switch ($jobState->getStatus()) {
                case JobState::STATUS_FAILURE:
                case JobState::STATUS_ERROR:
                    $jobsToRerun[] = $jobId;
                    break;
                case JobState::STATUS_TRIGGERED:
                case JobState::STATUS_RUNNING:
                    if ($jobState->getTriggeredAt()->getDateTimeObject() < $before) {
                        // The worker died (or the message was lost) while running it.
                        $jobsToRerun[] = $jobId;
                    } else {
                        $analyzerInProgress = true;
                    }
                    break;
            }
        }

        if (!empty($jobsToRerun)) {
            if (!$dryRun) {
                foreach ($jobsToRerun as $jobId) {
                    $this->workflowOrchestrator->rerunJobs($workflowId, $jobId);
                }
            }

            return $this->result($file, $asset, FileAnalysisUnblockAction::JobRerun, sprintf('analyzer job(s) %s did not complete', implode(', ', $jobsToRerun)), $workflowId);
        }

        if ($analyzerInProgress) {
            return $this->result($file, $asset, FileAnalysisUnblockAction::Skipped, 'analyzer job is in progress', $workflowId);
        }

        if ($analyzerTriggered) {
            // Succeeded or skipped (job "if" condition): nothing to rerun, the file is legitimately left unanalyzed.
            return $this->result($file, $asset, FileAnalysisUnblockAction::Skipped, 'analyzer job ended without analyzing the file (skipped by its condition?)', $workflowId);
        }

        // The analyzer never ran in this workflow.
        if ($this->hasFailedJob($workflow)) {
            if (!$dryRun) {
                $this->workflowOrchestrator->retryFailedJobs($workflowId);
            }

            return $this->result($file, $asset, FileAnalysisUnblockAction::FailedJobsRetried, 'a dependency of the analyzer job failed', $workflowId);
        }

        if (!$dryRun) {
            if (ModelWorkflowState::STATUS_STARTED === $workflow->getStatus()) {
                // Stale run that will never reach the analyzer: close it before starting over.
                $this->workflowOrchestrator->cancelWorkflow($workflowId);
            }
            $this->dispatchIngest($asset);
        }

        $reason = ModelWorkflowState::STATUS_STARTED === $workflow->getStatus()
            ? 'ingest workflow stalled before reaching the analyzer'
            : 'latest ingest workflow never ran the analyzer (integration added later?)';

        return $this->result($file, $asset, FileAnalysisUnblockAction::IngestDispatched, $reason, $workflowId);
    }

    private function findLatestIngestWorkflow(Asset $asset): ?WorkflowState
    {
        return $this->em->createQueryBuilder()
            ->select('w')
            ->from(WorkflowState::class, 'w')
            ->andWhere('w.asset = :asset')
            ->andWhere('w.name = :name')
            ->setParameter('asset', $asset->getId())
            ->setParameter('name', AssetIngestWorkflowEvent::getWorkflowName($asset->getWorkspaceId()))
            ->orderBy('w.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return string[] IDs of the analyzer jobs of the workspace ingest workflow
     */
    private function getAnalyzerJobIds(Asset $asset): array
    {
        $workspaceIntegrations = $this->em->getRepository(WorkspaceIntegration::class)->findBy([
            'workspace' => $asset->getWorkspaceId(),
            'integration' => FileAnalyzerIntegration::getName(),
            'enabled' => true,
        ]);

        return array_map(
            fn (WorkspaceIntegration $workspaceIntegration): string => WorkflowHelper::getJobIdPrefix(
                $this->integrationManager->getIntegrationConfiguration($workspaceIntegration)
            ),
            $workspaceIntegrations
        );
    }

    private function hasFailedJob(WorkflowState $workflow): bool
    {
        $definition = $this->workflowRepository->loadWorkflowByName($workflow->getName());
        if (null === $definition) {
            return false;
        }

        foreach ($definition->getJobIds() as $jobId) {
            $jobState = $this->stateRepository->getLastJobState($workflow->getId(), $jobId);
            if (null !== $jobState && in_array($jobState->getStatus(), [
                JobState::STATUS_FAILURE,
                JobState::STATUS_ERROR,
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private function dispatchIngest(Asset $asset): void
    {
        $this->workflowOrchestrator->dispatchEvent(
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
            [
                WorkflowState::INITIATOR_ID => $asset->getOwnerId(),
            ]
        );
    }

    private function result(File $file, Asset $asset, FileAnalysisUnblockAction $action, string $reason, ?string $workflowId = null): FileAnalysisUnblockResult
    {
        return new FileAnalysisUnblockResult($file->getId(), $asset->getId(), $action, $reason, $workflowId);
    }
}
