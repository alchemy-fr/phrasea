<?php

declare(strict_types=1);

namespace App\Tests\Functional\Asset;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use App\Consumer\Handler\File\ImportFile;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Workflow\WorkflowState;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Integration\Core\ReadMetadata\ReadMetadataIntegration;
use App\Service\Asset\FileImporter;
use App\Service\Asset\WorkspaceAssetIngester;
use App\Service\Workflow\Action\ImportSourceFileAction;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Files created from a URL with "importFile": the source of an asset is imported
 * by the ingest workflow, a rendition file by an ImportFile message.
 */
final class FileImportTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string EDITOR = KeycloakClientTestMock::USER_UID;
    private const string IMPORT_JOB_ID = 'import_source';

    private InMemoryTransport $queue;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        // Keep the workflow jobs queued: running them would download the URLs.
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');
        $this->queue = $transport->intercept();
    }

    public function testAssetSourceIsFlaggedAndLeftToTheIngestWorkflow(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);

        $response = $this->jsonRequest('POST', '/assets', self::EDITOR, [
            'workspace' => '/workspaces/'.$ws->getId(),
            'sourceFile' => self::importedSourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(201);

        $asset = self::getEntityManager()->find(Asset::class, $response->toArray()['id']);
        $this->assertTrue($asset->getSource()->isImportPending());
        $this->assertSame([], $this->getImportedFileIds());
    }

    public function testRenditionFileIsFlaggedAndImportedByMessage(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);

        $response = $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'name' => 'Preview',
            'sourceFile' => self::importedSourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(201);

        $file = self::getEntityManager()->find(AssetRendition::class, $response->toArray()['id'])->getFile();
        $this->assertTrue($file->isImportPending());
        $this->assertSame([$file->getId()], $this->getImportedFileIds());
    }

    public function testNotFlaggedWithoutImportFile(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);

        $response = $this->jsonRequest('POST', '/assets', self::EDITOR, [
            'workspace' => '/workspaces/'.$ws->getId(),
            'sourceFile' => ['importFile' => false] + self::importedSourceFile(),
        ]);
        $this->assertResponseStatusCodeSame(201);

        $asset = self::getEntityManager()->find(Asset::class, $response->toArray()['id']);
        $this->assertFalse($asset->getSource()->isImportPending());
    }

    public function testIntegrationsWaitForTheSourceImport(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $integration = $this->createAnalyzerIntegration($ws);

        $jobs = self::getService(WorkflowRepositoryInterface::class)
            ->loadWorkflowByName(AssetIngestWorkflowEvent::getWorkflowName($ws->getId()))
            ->getJobs();

        $importJob = $jobs->offsetGet(self::IMPORT_JOB_ID);
        $this->assertSame([], $importJob->getNeeds()->getArrayCopy());
        $this->assertSame(ImportSourceFileAction::class.'::evaluateIf', $importJob->getIf());
        $this->assertTrue($importJob->isContinueOnSkip());
        // The import is the only root: every other job runs after it, directly or not.
        foreach ($jobs as $jobId => $job) {
            if (self::IMPORT_JOB_ID !== $jobId) {
                $this->assertNotEmpty($job->getNeeds()->getArrayCopy(), $jobId);
            }
        }
        $this->assertSame([self::IMPORT_JOB_ID], $jobs->offsetGet($this->getAnalyzerJobId($integration))->getNeeds()->getArrayCopy());
    }

    public function testIntegrationsRunWhenThereIsNothingToImport(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $integration = $this->createAnalyzerIntegration($ws);
        $asset = $this->createAsset(['workspace' => $ws, 'no_flush' => true]);
        $asset->setSource($this->createUrlFile($ws));
        self::getEntityManager()->flush();

        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');
        $transport->intercept(false);
        self::getService(WorkspaceAssetIngester::class)->ingestAsset($asset, null);

        $workflow = self::getEntityManager()->getRepository(WorkflowState::class)->findOneBy(['asset' => $asset->getId()]);
        $stateRepository = self::getService(StateRepositoryInterface::class);
        $importState = $stateRepository->getLastJobState($workflow->getId(), self::IMPORT_JOB_ID);
        $this->assertSame(JobState::STATUS_SKIPPED, $importState->getStatus());
        $this->assertSame('No source file import pending', $importState->getSkipReason());
        $this->assertNotNull($stateRepository->getLastJobState($workflow->getId(), $this->getAnalyzerJobId($integration)), 'the analyzer ran after the skipped import');
    }

    public function testJobsSkippedForAnAssetWithoutSourceGiveTheirReason(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $ws]);

        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');
        $transport->intercept(false);
        self::getService(WorkspaceAssetIngester::class)->ingestAsset($asset, null);

        $workflow = self::getEntityManager()->getRepository(WorkflowState::class)->findOneBy(['asset' => $asset->getId()]);
        $readMetadataJobId = sprintf('%s:%s:extract', ReadMetadataIntegration::getName(), $defaults->integrations['readMetadata']->getId());
        $jobState = self::getService(StateRepositoryInterface::class)->getLastJobState($workflow->getId(), $readMetadataJobId);

        $this->assertSame(JobState::STATUS_SKIPPED, $jobState->getStatus());
        $this->assertSame('Asset has no source file', $jobState->getSkipReason());
    }

    public function testImportSourceFileActionRunsOnlyForAPendingSource(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $em = self::getEntityManager();

        $pending = $this->createUrlFile($ws);
        $pending->setImportPending(true);
        $pendingAsset = $this->createAsset(['workspace' => $ws, 'no_flush' => true]);
        $pendingAsset->setSource($pending);

        $notPendingAsset = $this->createAsset(['workspace' => $ws, 'no_flush' => true]);
        $notPendingAsset->setSource($this->createUrlFile($ws));

        $noSourceAsset = $this->createAsset(['workspace' => $ws]);
        $em->flush();

        $importer = $this->createMock(FileImporter::class);
        $importer->expects($this->once())
            ->method('importFile')
            ->with($this->callback(static fn (File $file): bool => $file->getId() === $pending->getId()));
        $action = new ImportSourceFileAction($importer, $em);

        $inputs = static fn (Asset $asset): Inputs => new Inputs(['assetId' => $asset->getId()]);
        $ifContext = function (Asset $asset) use ($inputs): JobExecutionContext {
            $context = $this->createMock(JobExecutionContext::class);
            $context->method('getInputs')->willReturn($inputs($asset));

            return $context;
        };

        $this->assertTrue($action->evaluateIf($ifContext($pendingAsset)));
        $this->assertFalse($action->evaluateIf($ifContext($notPendingAsset)));
        $this->assertFalse($action->evaluateIf($ifContext($noSourceAsset)));

        $runContext = $this->createMock(RunContext::class);
        $runContext->method('getInputs')->willReturn($inputs($pendingAsset));
        $action->handle($runContext);
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
        self::getEntityManager()->flush();

        return $integration;
    }

    private function getAnalyzerJobId(WorkspaceIntegration $integration): string
    {
        return FileAnalyzerIntegration::getName().':'.$integration->getId();
    }

    private static function importedSourceFile(): array
    {
        return [
            'url' => 'https://cdn.example.com/remote.jpg',
            'originalName' => 'remote.jpg',
            'type' => 'image/jpeg',
            'isPrivate' => false,
            'importFile' => true,
        ];
    }

    /**
     * @return string[]
     */
    private function getImportedFileIds(): array
    {
        $ids = [];
        foreach ($this->queue->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof ImportFile) {
                $ids[] = $message->getFileId();
            }
        }

        return $ids;
    }
}
