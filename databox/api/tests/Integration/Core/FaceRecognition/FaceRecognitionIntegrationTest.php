<?php

declare(strict_types=1);

namespace App\Tests\Integration\Core\FaceRecognition;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetFace;
use App\Entity\Core\Attribute;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceRecognitionAnalyzer;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationDataManager;
use App\Integration\IntegrationManager;
use App\Repository\Core\AssetFaceRepository;
use App\Service\Face\FaceRecognitionClient;
use App\Tests\AbstractDataboxTestCase;
use App\Tests\Face\FaceRecognitionClientMock;

class FaceRecognitionIntegrationTest extends AbstractDataboxTestCase
{
    private const array BOX_LEFT = ['x' => 0.1, 'y' => 0.2, 'w' => 0.2, 'h' => 0.3];
    private const array BOX_RIGHT = ['x' => 0.6, 'y' => 0.2, 'w' => 0.2, 'h' => 0.3];

    private const array ALICE = [1.0, 0.0, 0.0, 0.0];
    private const array ALICE_LOOKALIKE = [0.95, 0.05, 0.0, 0.0];
    private const array BOB = [0.0, 1.0, 0.0, 0.0];
    private const array STRANGER = [0.0, 0.0, 1.0, 0.0];

    public function testDetectIdentifyRecognizeAndPropagate(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();
        $attrDef = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Persons',
            'slug' => 'persons',
            'multiple' => true,
        ]);

        $wsIntegration = $this->createIntegration($workspace, [
            'attribute' => 'persons',
            'matchThreshold' => 0.6,
        ]);
        $config = self::getService(IntegrationManager::class)->getIntegrationConfiguration($wsIntegration);

        /** @var FaceRecognitionAnalyzer $analyzer */
        $analyzer = self::getService(FaceRecognitionAnalyzer::class);
        /** @var FaceRecognitionClientMock $client */
        $client = self::getService(FaceRecognitionClient::class);
        /** @var AssetFaceRepository $faceRepository */
        $faceRepository = self::getService(AssetFaceRepository::class);
        /** @var IntegrationDataManager $dataManager */
        $dataManager = self::getService(IntegrationDataManager::class);

        $groupPhoto = $this->createImageAsset($workspace);
        $otherPhoto = $this->createImageAsset($workspace);

        // 1. Detection keeps confident faces only; nobody is known yet
        $client->setFaces([
            self::face(self::BOX_LEFT, self::ALICE),
            self::face(self::BOX_RIGHT, self::BOB),
            self::face(['x' => 0.4, 'y' => 0.8, 'w' => 0.1, 'h' => 0.1], self::STRANGER, 0.2),
        ]);
        $summary = $analyzer->analyze($groupPhoto, $config);
        $this->assertNotNull($summary);
        $this->assertCount(2, $summary['faces']);
        $this->assertNull($summary['faces'][0]['identity']);
        $this->assertNull($summary['faces'][1]['identity']);
        $this->assertCount(1, $client->getCalls());
        $this->assertCount(2, $faceRepository->findByAsset($groupPhoto->getId()));
        $this->assertCount(0, $this->getPersonAttributes($groupPhoto->getId(), $attrDef->getId()));

        // 2. A user identifies the left face: the attribute is filled
        $aliceFace = $em->find(AssetFace::class, $summary['faces'][0]['id']);
        $this->assertInstanceOf(AssetFace::class, $aliceFace);
        $summary = $analyzer->identify($aliceFace, ' Alice ', $config);
        $this->assertSame('Alice', $summary['faces'][0]['identity']);
        $this->assertSame(AssetFace::IDENTITY_ORIGIN_USER, $summary['faces'][0]['identityOrigin']);
        $this->assertSame(1.0, $summary['faces'][0]['identityConfidence']);

        $attributes = $this->getPersonAttributes($groupPhoto->getId(), $attrDef->getId());
        $this->assertCount(1, $attributes);
        $this->assertSame('Alice', $attributes[0]->getValue());
        $this->assertSame(FaceRecognitionIntegration::getName(), $attributes[0]->getOriginVendor());
        $this->assertCount(1, $attributes[0]->getAssetAnnotations());

        // 3. Another asset with a look-alike of Alice and a stranger: Alice is recognized
        $client->setFaces([
            self::face(self::BOX_LEFT, self::ALICE_LOOKALIKE),
            self::face(self::BOX_RIGHT, self::STRANGER),
        ]);
        $summary = $analyzer->analyze($otherPhoto, $config);
        $this->assertNotNull($summary);
        $this->assertCount(2, $summary['faces']);
        $this->assertSame('Alice', $summary['faces'][0]['identity']);
        $this->assertSame(AssetFace::IDENTITY_ORIGIN_AUTO, $summary['faces'][0]['identityOrigin']);
        $this->assertGreaterThanOrEqual(0.6, $summary['faces'][0]['identityConfidence']);
        $this->assertNull($summary['faces'][1]['identity']);

        $data = $dataManager->getData($wsIntegration, null, $otherPhoto, FaceRecognitionAnalyzer::DATA_FACES);
        $this->assertNotNull($data);
        $decoded = json_decode((string) $data->getValue(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Alice', $decoded['faces'][0]['identity']);

        $attributes = $this->getPersonAttributes($otherPhoto->getId(), $attrDef->getId());
        $this->assertCount(1, $attributes);
        $this->assertSame('Alice', $attributes[0]->getValue());

        // 4. Re-running the detection on the group photo keeps what the user said
        $client->setFaces([
            self::face(self::BOX_LEFT, self::ALICE),
            self::face(self::BOX_RIGHT, self::BOB),
        ]);
        $summary = $analyzer->analyze($groupPhoto, $config);
        $this->assertNotNull($summary);
        $this->assertSame('Alice', $summary['faces'][0]['identity']);
        $this->assertSame(AssetFace::IDENTITY_ORIGIN_USER, $summary['faces'][0]['identityOrigin']);
        $this->assertNull($summary['faces'][1]['identity']);
        $this->assertCount(2, $faceRepository->findByAsset($groupPhoto->getId()), 'Previous faces are replaced');

        // 5. Identifying the right face as Bob and propagating leaves the stranger alone,
        //    but changing Alice's name propagates to the look-alike
        $bobFace = $em->find(AssetFace::class, $summary['faces'][1]['id']);
        $analyzer->identify($bobFace, 'Bob', $config);
        $this->assertSame(0, $analyzer->propagateIdentity($bobFace, $config));

        $aliceFace = $em->find(AssetFace::class, $summary['faces'][0]['id']);
        $analyzer->identify($aliceFace, 'Alice Smith', $config);
        $this->assertSame(1, $analyzer->propagateIdentity($aliceFace, $config));

        $em->clear();
        $otherFaces = $faceRepository->findByAsset($otherPhoto->getId());
        $this->assertCount(2, $otherFaces);
        $identities = array_map(fn (AssetFace $f): ?string => $f->getIdentity(), $otherFaces);
        $this->assertContains('Alice Smith', $identities);
        $this->assertContains(null, $identities);
        $derived = array_values(array_filter($otherFaces, fn (AssetFace $f): bool => 'Alice Smith' === $f->getIdentity()));
        $this->assertSame($aliceFace->getId(), $derived[0]->getReference()?->getId(), 'The derived face points to its reference');

        $attributes = $this->getPersonAttributes($otherPhoto->getId(), $attrDef->getId());
        $this->assertCount(1, $attributes);
        $this->assertSame('Alice Smith', $attributes[0]->getValue());

        // 6. Clearing an identity removes the attribute value and, once propagated, the derived identities
        $groupFaces = $faceRepository->findByAsset($groupPhoto->getId());
        foreach ($groupFaces as $face) {
            if ('Bob' === $face->getIdentity()) {
                $analyzer->identify($face, '', $config);
            }
        }
        $values = array_map(fn (Attribute $a): string => $a->getValue(), $this->getPersonAttributes($groupPhoto->getId(), $attrDef->getId()));
        $this->assertSame(['Alice Smith'], $values);

        $aliceFace = $em->find(AssetFace::class, $aliceFace->getId());
        $analyzer->identify($aliceFace, null, $config);
        $this->assertSame(0, $analyzer->revokeIdentity($bobFace, $config), 'Nothing was derived from Bob');
        $this->assertSame(1, $analyzer->revokeIdentity($aliceFace, $config));

        $em->clear();
        $identities = array_map(fn (AssetFace $f): ?string => $f->getIdentity(), $faceRepository->findByAsset($otherPhoto->getId()));
        $this->assertSame([null, null], $identities);
        $this->assertCount(0, $this->getPersonAttributes($otherPhoto->getId(), $attrDef->getId()));
        $this->assertCount(0, $this->getPersonAttributes($groupPhoto->getId(), $attrDef->getId()));
    }

    public function testAssetWithoutImageIsSkipped(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $wsIntegration = $this->createIntegration($workspace);
        $config = self::getService(IntegrationManager::class)->getIntegrationConfiguration($wsIntegration);
        $this->assertInstanceOf(IntegrationConfig::class, $config);

        /** @var FaceRecognitionClientMock $client */
        $client = self::getService(FaceRecognitionClient::class);
        $calls = count($client->getCalls());

        $asset = $this->createAsset(['workspace' => $workspace]);
        $this->assertNull(self::getService(FaceRecognitionAnalyzer::class)->analyze($asset, $config));
        $this->assertCount($calls, $client->getCalls(), 'The service is not called');
    }

    private static function face(array $box, array $embedding, float $confidence = 0.9): array
    {
        return [
            'box' => $box,
            'confidence' => $confidence,
            'embedding' => $embedding,
            'age' => 30,
            'gender' => 'F',
        ];
    }

    private function createIntegration(Workspace $workspace, array $config = []): WorkspaceIntegration
    {
        $em = self::getEntityManager();

        $integration = new WorkspaceIntegration();
        $integration->setPublic(true);
        $integration->setWorkspace($workspace);
        $integration->setName('Faces');
        $integration->setIntegration(FaceRecognitionIntegration::getName());
        $integration->setConfig($config);
        $integration->setOwnerId('custom_owner');
        $em->persist($integration);
        $em->flush();

        return $integration;
    }

    private function createImageAsset(Workspace $workspace): Asset
    {
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPathPublic(true);
        $file->setPath('test/'.uniqid().'.png');
        $file->setType('image/png');
        // Short-circuits the storage fetch
        $file->localTmpPath = __DIR__.'/../../../fixtures/files/alchemy.png';
        $em->persist($file);

        $asset = $this->createAsset([
            'workspace' => $workspace,
            'no_flush' => true,
        ]);
        $asset->setSource($file);
        $em->flush();

        return $asset;
    }

    /**
     * @return Attribute[]
     */
    private function getPersonAttributes(string $assetId, string $definitionId): array
    {
        return self::getEntityManager()->getRepository(Attribute::class)->findBy([
            'asset' => $assetId,
            'definition' => $definitionId,
        ], ['value' => 'ASC']);
    }
}
