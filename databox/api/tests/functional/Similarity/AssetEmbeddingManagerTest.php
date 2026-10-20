<?php

declare(strict_types=1);

namespace App\Tests\Functional\Similarity;

use Alchemy\Workflow\Model\Job;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Integration\Core\Similarity\SimilarityIntegration;
use App\Integration\IntegrationManager;
use App\Service\Storage\RenditionManager;
use App\Service\Vector\AssetEmbeddingManager;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * The embedding is computed from the configured rendition when it is an image,
 * from the thumbnail otherwise (the preview of a document is a PDF).
 */
final class AssetEmbeddingManagerTest extends AbstractDataboxTestCase
{
    private const string IMAGE = __DIR__.'/../../fixtures/files/alchemy.png';
    private const string OTHER_IMAGE = __DIR__.'/../../fixtures/files/Metadata_test_file.jpg';

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = $this->getOrCreateDefaultWorkspace();
    }

    public function testImagePreviewIsEmbedded(): void
    {
        $asset = $this->createAssetWithRenditions([
            'Preview' => ['image/png', self::IMAGE],
            'Thumbnail' => ['image/jpeg', self::OTHER_IMAGE],
        ]);

        $this->assertTrue($this->getManager()->embedAsset($asset, 'Preview'));
        $this->assertSame([self::IMAGE], $this->getEmbedder()->getCalls());
        $this->assertCount(EmbedderClientMock::DIMS, $this->getManager()->getAssetVector($asset));
    }

    public function testDocumentFallsBackToItsThumbnail(): void
    {
        $asset = $this->createAssetWithRenditions([
            'Preview' => ['application/pdf', '/not/fetched.pdf'],
            'Thumbnail' => ['image/png', self::IMAGE],
        ]);

        $this->assertTrue($this->getManager()->embedAsset($asset, 'Preview'));
        $this->assertSame([self::IMAGE], $this->getEmbedder()->getCalls());
    }

    public function testNoImageRenditionIsSkipped(): void
    {
        $asset = $this->createAssetWithRenditions([
            'Preview' => ['application/pdf', '/not/fetched.pdf'],
        ]);

        $this->assertFalse($this->getManager()->embedAsset($asset, 'Preview'));
        $this->assertSame([], $this->getEmbedder()->getCalls());
        $this->assertNull($this->getManager()->getAssetVector($asset));
    }

    public function testExistingEmbeddingIsKeptUnlessForced(): void
    {
        $asset = $this->createAssetWithRenditions([
            'Preview' => ['image/png', self::IMAGE],
        ]);
        $embedding = new AssetEmbedding();
        $embedding->setAsset($asset);
        $embedding->setVector([0.5, 0.5]);
        $embedding->setModel('previous');
        $em = self::getEntityManager();
        $em->persist($embedding);
        $em->flush();

        $this->assertFalse($this->getManager()->embedAsset($asset, 'Preview'));
        $this->assertSame([], $this->getEmbedder()->getCalls());

        $this->assertTrue($this->getManager()->embedAsset($asset, 'Preview', true));
        $this->assertCount(EmbedderClientMock::DIMS, $this->getManager()->getAssetVector($asset));
    }

    public function testEmbeddingJobWaitsForTheThumbnailToo(): void
    {
        /** @var RenditionManager $renditionManager */
        $renditionManager = self::getService(RenditionManager::class);
        $preview = $renditionManager->getRenditionDefinitionByName($this->workspace->getId(), 'Preview');
        $thumbnail = $renditionManager->getRenditionDefinitionByName($this->workspace->getId(), 'Thumbnail');

        $em = self::getEntityManager();
        $similarity = new WorkspaceIntegration();
        $similarity->setWorkspace($this->workspace);
        $similarity->setIntegration(SimilarityIntegration::getName());
        $similarity->setOwnerId('custom_owner');
        $similarity->setPublic(false);
        $similarity->setConfig(['rendition' => $preview->getId()]);
        $em->persist($similarity);
        $em->flush();
        $rendition = $em->getRepository(WorkspaceIntegration::class)->findOneBy([
            'workspace' => $this->workspace->getId(),
            'integration' => RenditionIntegration::getName(),
        ]);

        /** @var IntegrationManager $integrationManager */
        $integrationManager = self::getService(IntegrationManager::class);
        $similarityConfig = $integrationManager->getIntegrationConfiguration($similarity);
        $renditionConfig = $integrationManager->getIntegrationConfiguration($rendition);

        $this->assertSame([
            RenditionIntegration::getJobId($renditionConfig, $preview->getId()),
            RenditionIntegration::getJobId($renditionConfig, $thumbnail->getId()),
        ], $similarityConfig->getIntegration()->getNeededJobs($similarityConfig, $renditionConfig, new Job('similarity')));
    }

    /**
     * @param array<string, array{0: string, 1: string}> $renditions type and local path by rendition definition name
     */
    private function createAssetWithRenditions(array $renditions): Asset
    {
        $em = self::getEntityManager();
        $asset = $this->createAsset([
            'workspace' => $this->workspace,
            'no_flush' => true,
        ]);

        /** @var RenditionManager $renditionManager */
        $renditionManager = self::getService(RenditionManager::class);
        foreach ($renditions as $name => [$type, $path]) {
            $file = new File();
            $file->setWorkspace($this->workspace);
            $file->setStorage(File::STORAGE_S3_MAIN);
            $file->setPathPublic(true);
            $file->setPath('test/'.uniqid());
            $file->setType($type);
            // Short-circuits the storage fetch
            $file->localTmpPath = $path;
            $em->persist($file);

            $rendition = new AssetRendition();
            $rendition->setAsset($asset);
            $rendition->setDefinition($renditionManager->getRenditionDefinitionByName($this->workspace->getId(), $name));
            $rendition->setFile($file);
            $em->persist($rendition);
        }
        $em->flush();

        return $asset;
    }

    private function getManager(): AssetEmbeddingManager
    {
        return self::getService(AssetEmbeddingManager::class);
    }

    private function getEmbedder(): EmbedderClientMock
    {
        return self::getService(EmbedderClientMock::class);
    }
}
