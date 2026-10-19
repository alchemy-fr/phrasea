<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\Repository\StateRepositoryInterface;
use Alchemy\Workflow\State\WorkflowState as ModelWorkflowState;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\Similarity\SimilarityIntegration;
use App\Integration\IntegrationManager;
use App\Integration\WorkflowHelper;
use App\Service\Storage\RenditionManager;
use App\Service\Vector\AssetEmbeddingManager;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * `@similar = "<asset id>"` ranks the assets having an embedding by their
 * similarity to the given asset.
 */
class AssetSimilarSearchTest extends AbstractSearchTestCase
{
    private const int DIMS = 512;

    public function testSimilarCondition(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $create = fn (string $name, ?array $vector): Asset => $this->createEmbeddedAsset($workspace, $name, $vector);
        $reference = $create('Reference', self::vector([0 => 1.0]));
        $create('Close', self::vector([0 => 1.0, 1 => 0.2]));
        $create('Far', self::vector([1 => 1.0]));
        $notEmbedded = $create('Not embedded', null);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $this->assertSame(['Close', 'Far'], $this->search(sprintf('@similar = "%s"', $reference->getId())));
        $this->assertSame([], $this->search(sprintf('@similar = "%s"', $notEmbedded->getId())));
    }

    /**
     * The embedding job runs once the asset is already indexed (and again on a workflow rerun):
     * the document must follow.
     */
    public function testEmbeddingComputedAfterIndexationIsIndexed(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $reference = $this->createEmbeddedAsset($workspace, 'Reference', self::vector([0 => 1.0]));
        $late = $this->createEmbeddedAsset($workspace, 'Late', null);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
        $this->assertSame([], $this->search(sprintf('@similar = "%s"', $reference->getId())));

        $em = self::getEntityManager();
        $embedding = new AssetEmbedding();
        $embedding->setAsset($late);
        $embedding->setVector(self::vector([0 => 1.0, 1 => 0.5]));
        $embedding->setModel('test');
        $em->persist($embedding);
        $em->flush();
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
        $this->assertSame(['Late'], $this->search(sprintf('@similar = "%s"', $reference->getId())));

        // Rerun: the vector changes in place
        $em = self::getEntityManager();
        $embedding = $em->getRepository(AssetEmbedding::class)->findOneBy(['asset' => $late->getId()]);
        $embedding->setVector(self::vector([1 => 1.0]));
        $em->flush();
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
        $this->assertSame(self::vector([1 => 1.0]), $this->getIndexedEmbedding($late));
    }

    /**
     * A rerun of the similarity job from the run screen computes the embedding (from the
     * thumbnail of a document) and the asset document follows.
     */
    public function testRerunOfTheSimilarityJobIndexesTheEmbedding(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        /** @var RenditionManager $renditionManager */
        $renditionManager = self::getService(RenditionManager::class);
        $em = self::getEntityManager();
        $similarity = new WorkspaceIntegration();
        $similarity->setWorkspace($workspace);
        $similarity->setIntegration(SimilarityIntegration::getName());
        $similarity->setOwnerId(KeycloakClientTestMock::USER_UID);
        $similarity->setPublic(false);
        $similarity->setConfig(['rendition' => $renditionManager->getRenditionDefinitionByName($workspace->getId(), 'Preview')->getId()]);
        $em->persist($similarity);

        $document = $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Document',
            'public' => true,
            'ownerId' => KeycloakClientTestMock::USER_UID,
            'no_flush' => true,
        ]);
        $source = $this->createLocalFile($workspace, 'application/pdf', '/not/fetched.pdf');
        $document->setSource($source);
        foreach (['Main' => $source, 'Preview' => $source, 'Thumbnail' => $this->createLocalFile($workspace, 'image/png', __DIR__.'/../../fixtures/files/alchemy.png')] as $name => $file) {
            $rendition = new AssetRendition();
            $rendition->setAsset($document);
            $rendition->setDefinition($renditionManager->getRenditionDefinitionByName($workspace->getId(), $name));
            $rendition->setFile($file);
            $em->persist($rendition);
        }
        $em->flush();
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
        $this->assertNull($this->getIndexedEmbedding($document));

        /** @var StateRepositoryInterface $stateRepository */
        $stateRepository = self::getContainer()->get(StateRepositoryInterface::class);
        $state = new ModelWorkflowState(
            $stateRepository,
            'asset-ingest:'.$workspace->getId(),
            AssetIngestWorkflowEvent::createEvent($document->getId(), $workspace->getId()),
        );
        $stateRepository->persistWorkflowState($state);
        $jobId = WorkflowHelper::getJobIdPrefix(self::getService(IntegrationManager::class)->getIntegrationConfiguration($similarity));
        $stateRepository->persistJobState(new JobState($state->getId(), $jobId, JobState::STATUS_SKIPPED));

        $client = static::createClient();
        $client->request('POST', '/workflows/'.$state->getId().'/jobs/'.$jobId.'/rerun', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        self::waitForESIndex('asset');

        $this->assertNotNull(self::getService(AssetEmbeddingManager::class)->getAssetVector($document), 'Embedding computed');
        $this->assertNotNull($this->getIndexedEmbedding($document), 'Embedding indexed');
    }

    private function createLocalFile(Workspace $workspace, string $type, string $path): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPathPublic(true);
        $file->setPath('test/'.uniqid());
        $file->setType($type);
        // Short-circuits the storage fetch
        $file->localTmpPath = $path;
        self::getEntityManager()->persist($file);

        return $file;
    }

    private function getIndexedEmbedding(Asset $asset): ?array
    {
        return self::$documentIndices['asset']->getDocument($asset->getId())->getData()['embedding'] ?? null;
    }

    private function createEmbeddedAsset(Workspace $workspace, string $name, ?array $vector): Asset
    {
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'name' => $name,
            'public' => true,
            'no_flush' => true,
        ]);
        if (null !== $vector) {
            $embedding = new AssetEmbedding();
            $embedding->setAsset($asset);
            $embedding->setVector($vector);
            $embedding->setModel('test');
            self::getEntityManager()->persist($embedding);
        }
        self::getEntityManager()->flush();

        return $asset;
    }

    /**
     * @param array<int, float> $components
     *
     * @return float[]
     */
    private static function vector(array $components): array
    {
        $vector = array_fill(0, self::DIMS, 0.0);
        foreach ($components as $i => $value) {
            $vector[$i] = $value;
        }

        return $vector;
    }

    /**
     * @return string[] names of the results, in their order
     */
    private function search(string $condition): array
    {
        $response = self::createClient()->request('GET', '/assets', [
            'query' => ['conditions' => [$condition]],
        ]);
        $data = $this->getDataFromResponse($response, 200)['member'];

        return array_map(fn (array $asset): ?string => $asset['name'] ?? null, $data);
    }
}
