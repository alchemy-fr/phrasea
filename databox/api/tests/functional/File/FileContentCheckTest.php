<?php

declare(strict_types=1);

namespace App\Tests\Functional\File;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Upload\ContentTypeDetector;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\Workflow\Executor\EnvContainer;
use Alchemy\Workflow\Executor\RunContext;
use Alchemy\Workflow\Repository\WorkflowRepositoryInterface;
use Alchemy\Workflow\State\Inputs;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Outputs;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use App\Api\Mapper\Output\FileOutputMapper;
use App\Api\Model\Output\FileOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\FileAnalysisStateEnum;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Workflow\WorkflowState;
use App\Integration\Core\FileAnalyzer\FileAnalyzerAction;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Service\Asset\FileFetcher;
use App\Service\Asset\WorkspaceAssetIngester;
use App\Service\Workflow\Action\CheckFileContentAction;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The check_content job of the asset ingest: the content of the source file
 * against its declared type (see ContentTypeDetectorTest for the detection).
 */
final class FileContentCheckTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string EDITOR = KeycloakClientTestMock::USER_UID;
    private const string CHECK_CONTENT_JOB_ID = 'check_content';
    private const string HTML = '<!DOCTYPE html><html><body><script>alert(document.cookie)</script></body></html>';
    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
    }

    public function testHtmlDeclaredAsAnImageIsRejected(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAssetWithSource($ws, self::HTML, 'image/jpeg', 'photo.jpg');

        $context = $this->checkContent($asset);

        $this->assertSame(JobState::STATUS_FAILURE, $context->getEndStatus());
        $file = $this->reloadSource($asset);
        $this->assertTrue($file->isContentRejected());
        $this->assertFalse($file->isAccepted());
        $this->assertSame('text/html', $file->getDetectedType());
        $this->assertSame('image/jpeg', $file->getType(), 'the declared type is kept');
        $this->assertSame(FileAnalysisStateEnum::Failed, $file->getAnalysisState());
        $this->assertSame('Declared type "image/jpeg", content is "text/html"', $file->getAnalysis()->getMessage());
        $this->assertSame(AssetStatusEnum::Quarantined, $this->reloadAsset($asset)->getStatus());

        $output = $this->transform($this->reloadSource($asset));
        $this->assertNull($output->getUrl(), 'the content is not served');
        $this->assertFalse($output->accepted, 'refused even when the workspace does not enforce the analysis');
        $this->assertSame('Declared type "image/jpeg", content is "text/html"', $output->analysis['message'] ?? null);
    }

    public function testImageDeclaredAsAnImageIsAccepted(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAssetWithSource($ws, self::PNG, 'image/png', 'pixel.png');

        $context = $this->checkContent($asset);

        $this->assertNull($context->getEndStatus());
        $file = $this->reloadSource($asset);
        $this->assertFalse($file->isContentRejected());
        $this->assertTrue($file->isAccepted());
        $this->assertSame('image/png', $file->getDetectedType());
        $this->assertSame(FileAnalysisStateEnum::NotAnalyzed, $file->getAnalysisState(), 'left to the analyzers');
        $this->assertNotNull($this->transform($file)->getUrl());
        $this->assertSame(AssetStatusEnum::Accepted, $this->reloadAsset($asset)->getStatus());
    }

    public function testPlaylistIsRejected(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAssetWithSource($ws, "#EXTM3U\n#EXTINF:10,\nhttp://169.254.169.254/latest/meta-data/\n", 'video/mp4', 'video.mp4');

        $context = $this->checkContent($asset);

        $this->assertSame(JobState::STATUS_FAILURE, $context->getEndStatus());
        $file = $this->reloadSource($asset);
        $this->assertTrue($file->isContentRejected());
        $this->assertStringContainsString('HLS playlist', $file->getAnalysis()->getMessage());
        $this->assertSame(AssetStatusEnum::Quarantined, $this->reloadAsset($asset)->getStatus());
    }

    public function testTypeNotAllowedIsRejected(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAssetWithSource($ws, self::PNG, 'image/jpeg', 'photo.jpg');

        $context = $this->checkContent($asset, 'image/jpeg(.jpg)');

        $this->assertSame(JobState::STATUS_FAILURE, $context->getEndStatus());
        $file = $this->reloadSource($asset);
        $this->assertSame('image/png', $file->getDetectedType());
        $this->assertStringContainsString('File type "image/png" with extension "jpg" is not allowed', $file->getAnalysis()->getMessage());
    }

    public function testAnAnalysisDoesNotAcceptARejectedContent(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $integration = $this->createAnalyzerIntegration($ws);
        $asset = $this->createAssetWithSource($ws, self::HTML, 'image/jpeg', 'photo.jpg');
        $this->checkContent($asset);

        $context = $this->createRunContext([
            'assetId' => $asset->getId(),
            'integrationId' => $integration->getId(),
            'rerun' => true,
        ]);
        /** @var FileAnalyzerAction $analyzer */
        $analyzer = self::getService(FileAnalyzerAction::class);
        $analyzer->doHandle($context);

        $this->assertSame(JobState::STATUS_FAILURE, $context->getEndStatus());
        $file = $this->reloadSource($asset);
        $this->assertTrue($file->isContentRejected());
        $this->assertFalse($file->isAccepted());
        $this->assertSame('Declared type "image/jpeg", content is "text/html"', $file->getAnalysis()->getMessage(), 'the refusal is not replaced');
        $this->assertSame(AssetStatusEnum::Quarantined, $this->reloadAsset($asset)->getStatus());
    }

    public function testBypassIsKeptByTheNextCheck(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $asset = $this->createAssetWithSource($ws, self::HTML, 'image/jpeg', 'photo.jpg');
        $this->checkContent($asset);

        self::getEntityManager()->clear();
        $this->jsonRequest('POST', '/assets/'.$asset->getId().'/quarantine-bypass', KeycloakClientTestMock::ADMIN_UID, []);
        $this->assertResponseIsSuccessful();

        $context = $this->checkContent($asset);

        $this->assertNull($context->getEndStatus());
        $file = $this->reloadSource($asset);
        $this->assertFalse($file->isContentRejected());
        $this->assertTrue($file->isAccepted());
        $this->assertSame(FileAnalysisStateEnum::Bypassed, $file->getAnalysisState());
        $this->assertSame(AssetStatusEnum::Accepted, $this->reloadAsset($asset)->getStatus());
    }

    public function testNoIntegrationRunsAfterARejection(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->createAnalyzerIntegration($ws);
        $asset = $this->createAssetWithSource($ws, self::HTML, 'image/jpeg', 'photo.jpg');

        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');
        $transport->intercept(false);
        self::getService(WorkspaceAssetIngester::class)->ingestAsset($asset, null);

        $workflow = self::getEntityManager()->getRepository(WorkflowState::class)->findOneBy(['asset' => $asset->getId()]);
        $stateRepository = self::getService(StateRepositoryInterface::class);
        $this->assertSame(JobState::STATUS_FAILURE, $stateRepository->getLastJobState($workflow->getId(), self::CHECK_CONTENT_JOB_ID)->getStatus());

        $jobIds = self::getService(WorkflowRepositoryInterface::class)
            ->loadWorkflowByName(AssetIngestWorkflowEvent::getWorkflowName($ws->getId()))
            ->getJobIds();
        $ran = array_values(array_filter($jobIds, static fn (string $jobId): bool => null !== $stateRepository->getLastJobState($workflow->getId(), $jobId)));
        $this->assertSame(['import_source', self::CHECK_CONTENT_JOB_ID], $ran, 'no rendition nor analysis ran');
        $this->assertTrue($this->reloadSource($asset)->isContentRejected());
    }

    public function testDirectUploadOfANotAllowedTypeIsRefused(): void
    {
        // Read by a container booted after it is set
        $_ENV['ALLOWED_FILE_TYPES'] = $_SERVER['ALLOWED_FILE_TYPES'] = 'image/jpeg(.jpg,.jpeg)';
        try {
            self::ensureKernelShutdown();
            // bootKernel() reuses a kernel, even shut down
            static::$kernel = null;
            self::bootKernel();
            [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);

            $response = static::createClient()->request('POST', '/assets', [
                'headers' => [
                    'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::EDITOR),
                ],
                'json' => [
                    'workspace' => '/workspaces/'.$ws->getId(),
                ],
                'extra' => [
                    'files' => [
                        'file' => new UploadedFile(__DIR__.'/../../fixtures/files/alchemy.png', 'alchemy.png', 'image/png'),
                    ],
                ],
            ]);
            $this->assertResponseStatusCodeSame(400);
            $this->assertStringContainsString('File type "image/png" with extension "png" is not allowed', $response->toArray(false)['detail'] ?? '');
        } finally {
            unset($_ENV['ALLOWED_FILE_TYPES'], $_SERVER['ALLOWED_FILE_TYPES']);
        }
    }

    private function createAssetWithSource(Workspace $workspace, string $content, string $type, string $name): Asset
    {
        $em = self::getEntityManager();
        $path = sprintf('test/%s.%s', uniqid(), pathinfo($name, PATHINFO_EXTENSION));
        self::getService(FileStorageManager::class)->store($path, $content);

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath($path);
        $file->setType($type);
        $file->setOriginalName($name);
        $file->setExtension(pathinfo($name, PATHINFO_EXTENSION));
        $file->setSize(strlen($content));
        $em->persist($file);

        $asset = $this->createAsset(['workspace' => $workspace, 'ownerId' => self::EDITOR, 'no_flush' => true]);
        $asset->setSource($file);
        $em->flush();

        return $asset;
    }

    private function checkContent(Asset $asset, string $allowedFileTypes = ''): RunContext
    {
        $action = new CheckFileContentAction(
            self::getEntityManager(),
            self::getService(FileFetcher::class),
            new FileValidator($allowedFileTypes),
            new ContentTypeDetector(),
        );
        $context = $this->createRunContext(['assetId' => $asset->getId()]);
        $action->handle($context);

        return $context;
    }

    private function createRunContext(array $inputs): RunContext
    {
        return new RunContext(
            new JobState('workflow-id', 'job-id', JobState::STATUS_RUNNING),
            new NullOutput(),
            new Inputs($inputs),
            new EnvContainer(),
            new Outputs(),
        );
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

    private function reloadAsset(Asset $asset): Asset
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(Asset::class, $asset->getId());
    }

    private function reloadSource(Asset $asset): File
    {
        return $this->reloadAsset($asset)->getSource();
    }

    private function transform(File $file): FileOutput
    {
        /** @var FileOutput $output */
        $output = self::getService(FileOutputMapper::class)->map($file, ['groups' => [File::GROUP_LIST]]);

        return $output;
    }
}
