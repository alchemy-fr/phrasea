<?php

declare(strict_types=1);

namespace App\Tests\Functional\File;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Workflow\WorkflowState;
use App\File\FileAnalysisUnblockAction;
use App\File\FileAnalysisUnblocker;
use App\File\FileAnalysisUnblockResult;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Integration\IntegrationManager;
use App\Integration\WorkflowHelper;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The hourly safety net re-triggering the file analyses that never completed
 * (see App\Command\UnblockFileAnalysesCommand).
 */
class FileAnalysisUnblockerTest extends AbstractDataboxTestCase
{
    private const string OLD = '-2 hours';

    private \DateTimeImmutable $before;
    private InMemoryTransport $jobQueue;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->before = new \DateTimeImmutable('-1 hour');
        // Workflow jobs are dispatched on p2: keep them queued instead of running them.
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');
        $this->jobQueue = $transport->intercept();
    }

    public function testPendingFileWithoutAnyWorkflowGetsANewIngest(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();
        [$file, $asset] = $this->createPendingSourceFile($workspace);

        $results = $this->unblock();

        $this->assertActions([$file->getId() => FileAnalysisUnblockAction::IngestDispatched], $results);
        $this->assertNull($results[0]->workflowId);

        $workflow = $this->findLatestIngestWorkflow($asset);
        $this->assertNotNull($workflow, 'a new ingest workflow was started');
        $this->assertSame(AssetIngestWorkflowEvent::getWorkflowName($workspace->getId()), $workflow->getName());
        $this->assertNotEmpty($this->jobQueue->getSent(), 'its first jobs were queued');
    }

    public function testNothingToDoWhenFilesAreNotStuck(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();

        // Recently created: the ingest workflow is simply not there yet.
        $this->createPendingSourceFile($workspace, old: false);
        // Already analyzed.
        [$analyzed] = $this->createPendingSourceFile($workspace);
        $analyzed->setAnalysisResult(File::ANALYSIS_SUCCESS);
        // Not needing an analysis (e.g. a rendition file).
        [$notNeeded] = $this->createPendingSourceFile($workspace);
        $notNeeded->setNoAnalysisNeeded();
        // Source of a trashed asset only.
        [, $trashedAsset] = $this->createPendingSourceFile($workspace);
        $trashedAsset->setDeletedAt(new \DateTimeImmutable());
        // Not the source of any asset.
        $this->createFile($workspace);
        self::getEntityManager()->flush();

        $this->assertSame([], $this->unblock());
        $this->assertSame([], $this->jobQueue->getSent());
    }

    public function testFilesOfWorkspacesWithoutAnalyzerAreIgnored(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $this->assertNull($this->findAnalyzerIntegration($workspace), 'the default workspace runs no analyzer');
        $this->createPendingSourceFile($workspace);

        $this->assertSame([], $this->unblock());

        // A disabled analyzer does not count either.
        $workspace = self::getEntityManager()->find(Workspace::class, $workspace->getId());
        $integration = $this->createAnalyzerIntegration($workspace);
        $integration->setEnabled(false);
        self::getEntityManager()->flush();

        $this->assertSame([], $this->unblock());
    }

    public function testRecentOrCancelledWorkflowsAreLeftAlone(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();

        [$recent, $recentAsset] = $this->createPendingSourceFile($workspace);
        $this->createIngestWorkflow($recentAsset, old: false);

        [$cancelled, $cancelledAsset] = $this->createPendingSourceFile($workspace);
        $this->createIngestWorkflow($cancelledAsset, status: ModelWorkflowState::STATUS_CANCELLED);

        $results = $this->unblock();

        $this->assertActions([
            $recent->getId() => FileAnalysisUnblockAction::Skipped,
            $cancelled->getId() => FileAnalysisUnblockAction::Skipped,
        ], $results);
        $this->assertSame([], $this->jobQueue->getSent());
    }

    public function testFailedOrLostAnalyzerJobIsRerun(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();
        $analyzerJobId = $this->getAnalyzerJobId($workspace);

        [$failed, $failedAsset] = $this->createPendingSourceFile($workspace);
        $failedWorkflow = $this->createIngestWorkflow($failedAsset, status: ModelWorkflowState::STATUS_FAILURE);
        $this->createJobState($failedWorkflow, $analyzerJobId, JobState::STATUS_FAILURE);

        [$lost, $lostAsset] = $this->createPendingSourceFile($workspace);
        $lostWorkflow = $this->createIngestWorkflow($lostAsset);
        $this->createJobState($lostWorkflow, $analyzerJobId, JobState::STATUS_RUNNING);

        [$running, $runningAsset] = $this->createPendingSourceFile($workspace);
        $runningWorkflow = $this->createIngestWorkflow($runningAsset);
        $this->createJobState($runningWorkflow, $analyzerJobId, JobState::STATUS_RUNNING, old: false);

        $results = $this->unblock();

        $this->assertActions([
            $failed->getId() => FileAnalysisUnblockAction::JobRerun,
            $lost->getId() => FileAnalysisUnblockAction::JobRerun,
            $running->getId() => FileAnalysisUnblockAction::Skipped,
        ], $results);

        $stateRepository = $this->getStateRepository();
        foreach ([$failedWorkflow, $lostWorkflow] as $workflow) {
            $states = $stateRepository->getJobStates($workflow->getId(), $analyzerJobId);
            $this->assertCount(2, $states, 'the analyzer job was triggered again');
            $this->assertSame(JobState::STATUS_TRIGGERED, $states[0]->getStatus());
            $this->assertTrue($states[0]->getInputs()['rerun'] ?? null);
            $this->assertSame(ModelWorkflowState::STATUS_STARTED, $stateRepository->getWorkflowState($workflow->getId())->getStatus());
        }
        $this->assertCount(1, $stateRepository->getJobStates($runningWorkflow->getId(), $analyzerJobId));
        $this->assertCount(2, $this->jobQueue->getSent());
    }

    public function testFailedDependencyOfTheAnalyzerIsRetried(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();
        $analyzerJobId = $this->getAnalyzerJobId($workspace);
        $dependencyJobId = $this->getNonAnalyzerJobId($workspace);

        [$file, $asset] = $this->createPendingSourceFile($workspace);
        $workflow = $this->createIngestWorkflow($asset, status: ModelWorkflowState::STATUS_FAILURE);
        $this->createJobState($workflow, $dependencyJobId, JobState::STATUS_FAILURE);

        $results = $this->unblock();

        $this->assertActions([$file->getId() => FileAnalysisUnblockAction::FailedJobsRetried], $results);

        $stateRepository = $this->getStateRepository();
        $states = $stateRepository->getJobStates($workflow->getId(), $dependencyJobId);
        $this->assertCount(2, $states);
        $this->assertSame(JobState::STATUS_TRIGGERED, $states[0]->getStatus());
        $this->assertSame([], $stateRepository->getJobStates($workflow->getId(), $analyzerJobId), 'the analyzer waits for its dependency');
    }

    public function testWorkflowThatNeverReachedTheAnalyzerIsReplaced(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();

        // Ended before the analyzer integration was added to the workspace.
        [$ended, $endedAsset] = $this->createPendingSourceFile($workspace);
        $this->createIngestWorkflow($endedAsset, status: ModelWorkflowState::STATUS_SUCCESS);

        // Stalled: nothing failed, nothing runs, the analyzer was never triggered.
        [$stalled, $stalledAsset] = $this->createPendingSourceFile($workspace);
        $stalledWorkflow = $this->createIngestWorkflow($stalledAsset, status: ModelWorkflowState::STATUS_STARTED);

        $results = $this->unblock();

        $this->assertActions([
            $ended->getId() => FileAnalysisUnblockAction::IngestDispatched,
            $stalled->getId() => FileAnalysisUnblockAction::IngestDispatched,
        ], $results);

        $stateRepository = $this->getStateRepository();
        $this->assertSame(ModelWorkflowState::STATUS_CANCELLED, $stateRepository->getWorkflowState($stalledWorkflow->getId())->getStatus(), 'the stalled run was cancelled');

        foreach ([$endedAsset, $stalledAsset] as $asset) {
            $latest = $this->findLatestIngestWorkflow($asset);
            $this->assertSame(ModelWorkflowState::STATUS_STARTED, $latest->getStatus());
            $this->assertGreaterThan($this->before, $latest->getStartedAt(), 'a new ingest was started');
        }
    }

    public function testDryRunOnlyReports(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();
        $analyzerJobId = $this->getAnalyzerJobId($workspace);

        [$noWorkflow] = $this->createPendingSourceFile($workspace);
        [$failed, $failedAsset] = $this->createPendingSourceFile($workspace);
        $workflow = $this->createIngestWorkflow($failedAsset, status: ModelWorkflowState::STATUS_FAILURE);
        $this->createJobState($workflow, $analyzerJobId, JobState::STATUS_FAILURE);

        $results = $this->unblock(dryRun: true);

        $this->assertActions([
            $noWorkflow->getId() => FileAnalysisUnblockAction::IngestDispatched,
            $failed->getId() => FileAnalysisUnblockAction::JobRerun,
        ], $results);
        $this->assertSame([], $this->jobQueue->getSent());
        $this->assertCount(1, $this->getStateRepository()->getJobStates($workflow->getId(), $analyzerJobId));
        $this->assertSame(ModelWorkflowState::STATUS_FAILURE, $this->getStateRepository()->getWorkflowState($workflow->getId())->getStatus());
    }

    public function testLimitIsHonoredOldestFirst(): void
    {
        $workspace = $this->createWorkspaceWithAnalyzer();
        [$oldest] = $this->createPendingSourceFile($workspace, old: '-3 days');
        $this->createPendingSourceFile($workspace, old: '-2 days');

        $results = $this->unblock(limit: 1);

        $this->assertCount(1, $results);
        $this->assertSame($oldest->getId(), $results[0]->fileId);
    }

    /**
     * @return FileAnalysisUnblockResult[]
     */
    private function unblock(bool $dryRun = false, int $limit = FileAnalysisUnblocker::DEFAULT_LIMIT): array
    {
        self::getEntityManager()->clear();
        $this->getStateRepository()->flush();

        $results = self::getService(FileAnalysisUnblocker::class)->unblock($this->before, $limit, $dryRun);

        // Drop the cached states so that the assertions read the database.
        $this->getStateRepository()->flush();

        return $results;
    }

    /**
     * @param array<string, FileAnalysisUnblockAction> $expected fileId => action
     * @param FileAnalysisUnblockResult[]              $results
     */
    private function assertActions(array $expected, array $results): void
    {
        $actual = [];
        foreach ($results as $result) {
            $actual[$result->fileId] = $result->action;
        }
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual, 'one result per stuck file, with the expected action');
    }

    private function createWorkspaceWithAnalyzer(): Workspace
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        if (null === $this->findAnalyzerIntegration($workspace)) {
            $this->createAnalyzerIntegration($workspace);
            self::getEntityManager()->flush();
        }

        return $workspace;
    }

    private function findAnalyzerIntegration(Workspace $workspace): ?WorkspaceIntegration
    {
        return self::getEntityManager()->getRepository(WorkspaceIntegration::class)->findOneBy([
            'workspace' => $workspace->getId(),
            'integration' => FileAnalyzerIntegration::getName(),
        ]);
    }

    private function createAnalyzerIntegration(Workspace $workspace): WorkspaceIntegration
    {
        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setOwnerId($workspace->getOwnerId());
        $integration->setPublic(false);
        $integration->setIntegration(FileAnalyzerIntegration::getName());
        $integration->setConfig(['analyzers' => []]);
        self::getEntityManager()->persist($integration);

        return $integration;
    }

    private function getAnalyzerJobId(Workspace $workspace): string
    {
        $integration = $this->findAnalyzerIntegration($workspace);
        $this->assertNotNull($integration);

        return WorkflowHelper::getJobIdPrefix(
            self::getService(IntegrationManager::class)->getIntegrationConfiguration($integration)
        );
    }

    /**
     * A job of the workspace ingest workflow the analyzer may depend on (e.g. metadata extraction).
     */
    private function getNonAnalyzerJobId(Workspace $workspace): string
    {
        $workflow = self::getService(WorkflowRepositoryInterface::class)
            ->loadWorkflowByName(AssetIngestWorkflowEvent::getWorkflowName($workspace->getId()));
        $this->assertNotNull($workflow);

        foreach ($workflow->getJobIds() as $jobId) {
            if (!str_starts_with($jobId, FileAnalyzerIntegration::getName().':')) {
                return $jobId;
            }
        }

        $this->fail('The ingest workflow has no job besides the analyzer');
    }

    /**
     * @param string|false $old When not false, backdates the file creation by this relative interval
     *
     * @return array{File, Asset}
     */
    private function createPendingSourceFile(Workspace $workspace, string|false $old = self::OLD): array
    {
        $em = self::getEntityManager();

        $file = $this->createFile($workspace);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'no_flush' => true,
        ]);
        $asset->setSource($file);
        $em->flush();

        if (false !== $old) {
            $em->getConnection()->executeStatement(
                'UPDATE file SET created_at = :createdAt WHERE id = :id',
                ['createdAt' => new \DateTimeImmutable($old)->format('Y-m-d H:i:s'), 'id' => $file->getId()],
            );
            $em->refresh($file);
        }

        return [$file, $asset];
    }

    private function createFile(Workspace $workspace): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        self::getEntityManager()->persist($file);

        return $file;
    }

    private function createIngestWorkflow(Asset $asset, int $status = ModelWorkflowState::STATUS_FAILURE, bool $old = true): ModelWorkflowState
    {
        $stateRepository = $this->getStateRepository();
        $workspaceId = $asset->getWorkspaceId();

        $state = new ModelWorkflowState(
            $stateRepository,
            AssetIngestWorkflowEvent::getWorkflowName($workspaceId),
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $workspaceId),
        );
        $state->setStatus($status);
        if (ModelWorkflowState::STATUS_STARTED !== $status) {
            $state->setEndedAt(new MicroDateTime());
        }
        if ($old) {
            $state = $this->backdate($state, 'startedAt');
        }
        $stateRepository->persistWorkflowState($state);

        return $state;
    }

    private function createJobState(ModelWorkflowState $workflow, string $jobId, int $status, bool $old = true): JobState
    {
        $stateRepository = $this->getStateRepository();

        $jobState = $stateRepository->createJobState($workflow->getId(), $jobId);
        $jobState->setStatus($status);
        if ($old) {
            $jobState = $this->backdate($jobState, 'triggeredAt');
        }
        $stateRepository->persistJobState($jobState);

        return $jobState;
    }

    /**
     * The started/triggered dates are readonly and set to "now" by the constructors:
     * rebuild the state from its serialized form with the date moved to the past.
     *
     * @template T of ModelWorkflowState|JobState
     *
     * @param T $state
     *
     * @return T
     */
    private function backdate(ModelWorkflowState|JobState $state, string $dateField): ModelWorkflowState|JobState
    {
        $data = $state->__serialize();
        $data[$dateField] = new MicroDateTime(self::OLD);

        $backdated = new \ReflectionClass($state)->newInstanceWithoutConstructor();
        $backdated->__unserialize($data);
        if ($backdated instanceof ModelWorkflowState) {
            $backdated->setStateRepository($this->getStateRepository());
        }

        return $backdated;
    }

    private function findLatestIngestWorkflow(Asset $asset): ?WorkflowState
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->createQueryBuilder()
            ->select('w')
            ->from(WorkflowState::class, 'w')
            ->andWhere('w.asset = :asset')
            ->setParameter('asset', $asset->getId())
            ->orderBy('w.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function getStateRepository(): StateRepositoryInterface
    {
        return self::getService(StateRepositoryInterface::class);
    }
}
