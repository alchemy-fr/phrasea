<?php

declare(strict_types=1);

namespace App\Tests\Functional\Asset;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Inputs;
use App\Consumer\Handler\File\ImportFile;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Service\Asset\FileImporter;
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
        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($ws);
        $integration->setOwnerId($ws->getOwnerId());
        $integration->setPublic(false);
        $integration->setIntegration(FileAnalyzerIntegration::getName());
        $integration->setConfig(['analyzers' => []]);
        self::getEntityManager()->persist($integration);
        self::getEntityManager()->flush();

        $workflow = self::getService(WorkflowRepositoryInterface::class)
            ->loadWorkflowByName(AssetIngestWorkflowEvent::getWorkflowName($ws->getId()));

        $jobs = $workflow->getJobs();
        $this->assertTrue($jobs->offsetExists('import_source'));
        $this->assertSame([], $jobs->offsetGet('import_source')->getNeeds()->getArrayCopy());
        // The import is the only root: every other job runs after it, directly or not.
        foreach ($jobs as $jobId => $job) {
            if ('import_source' !== $jobId) {
                $this->assertNotEmpty($job->getNeeds()->getArrayCopy(), $jobId);
            }
        }
        $analyzerJobId = FileAnalyzerIntegration::getName().':'.$integration->getId();
        $this->assertSame(['import_source'], $jobs->offsetGet($analyzerJobId)->getNeeds()->getArrayCopy());
    }

    public function testImportSourceFileActionOnlyImportsAPendingSource(): void
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
        foreach ([$pendingAsset, $notPendingAsset, $noSourceAsset] as $asset) {
            $context = $this->createMock(RunContext::class);
            $context->method('getInputs')->willReturn(new Inputs(['assetId' => $asset->getId()]));
            $action->handle($context);
        }
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
