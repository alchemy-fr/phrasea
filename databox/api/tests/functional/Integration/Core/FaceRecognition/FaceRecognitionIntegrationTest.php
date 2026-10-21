<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Core\FaceRecognition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Traits\AssetAnnotationsInterface;
use App\Integration\Core\FaceRecognition\FaceManager;
use App\Integration\Core\FaceRecognition\FaceRecognitionAnalyzer;
use App\Integration\Core\FaceRecognition\FaceRecognitionClient;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use App\Repository\Core\AttributeRepository;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Attribute\AttributeApiTestTrait;
use App\Tests\Functional\Face\FaceRecognitionClientMock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Faces are values of the configured entity attribute, persons are the entities of its list.
 */
class FaceRecognitionIntegrationTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private const array BOX_LEFT = ['x' => 0.1, 'y' => 0.2, 'w' => 0.2, 'h' => 0.3];
    private const array BOX_RIGHT = ['x' => 0.6, 'y' => 0.2, 'w' => 0.2, 'h' => 0.3];

    private const array ALICE = [1.0, 0.0, 0.0, 0.0];
    private const array ALICE_LOOKALIKE = [0.95, 0.05, 0.0, 0.0];
    private const array BOB = [0.0, 1.0, 0.0, 0.0];
    private const array STRANGER = [0.0, 0.0, 1.0, 0.0];

    private Workspace $workspace;
    private EntityList $list;
    private AttributeDefinition $definition;

    public function testDetectNameRecognizeAndPropagate(): void
    {
        $em = self::getEntityManager();
        $this->setUpScene();
        $config = $this->createConfig(['matchThreshold' => 0.6]);

        $analyzer = self::getService(FaceRecognitionAnalyzer::class);
        /** @var FaceRecognitionClientMock $client */
        $client = self::getService(FaceRecognitionClient::class);
        $faceManager = self::getService(FaceManager::class);

        $groupPhoto = $this->createImageAsset();
        $otherPhoto = $this->createImageAsset();

        // 1. Detection keeps confident faces only; nobody is known yet: one pending person per face
        $client->setFaces([
            self::face(self::BOX_LEFT, self::ALICE),
            self::face(self::BOX_RIGHT, self::BOB),
            self::face(['x' => 0.4, 'y' => 0.8, 'w' => 0.1, 'h' => 0.1], self::STRANGER, 0.2),
        ]);
        $faces = $analyzer->analyze($groupPhoto, $config);
        $this->assertNotNull($faces);
        $this->assertCount(2, $faces);
        $this->assertCount(1, $client->getCalls());
        [$leftFace, $rightFace] = $faces;
        foreach ($faces as $face) {
            $annotations = $face->getAssetAnnotations() ?? [];
            $this->assertCount(1, $annotations, 'One annotation per face');
            $this->assertSame(AssetAnnotationsInterface::TYPE_FACE, $annotations[0]['type']);
        }
        $this->assertSame(self::BOX_LEFT, self::boxOf($leftFace));
        $this->assertSame(self::BOX_RIGHT, self::boxOf($rightFace));
        $this->assertSame(Attribute::ORIGIN_MACHINE, $leftFace->getOrigin());
        $this->assertSame(FaceRecognitionIntegration::getName(), $leftFace->getOriginVendor());
        $this->assertSame(self::ALICE, $leftFace->getEmbedding()['v']);
        $unknownAlice = $this->person($leftFace);
        $unknownBob = $this->person($rightFace);
        $this->assertNotSame($unknownAlice->getId(), $unknownBob->getId(), 'Different persons');
        $this->assertSame(AttributeEntity::STATUS_PENDING, $unknownAlice->getStatus());
        $this->assertStringStartsWith('Unknown person · ', $unknownAlice->getValue());
        $this->assertSame($this->list->getId(), $unknownAlice->getListId());

        // 2. A user names the left face through the attribute API: the face becomes a reference
        //    and the pending person it came from disappears
        $alice = $this->createEntity($this->list(), 'Alice');
        $this->api('PATCH', '/attributes/'.$leftFace->getId(), KeycloakClientTestMock::ADMIN_UID, ['value' => $alice->getId()]);
        $this->assertResponseStatusCodeSame(200);
        $em->clear();
        $leftFace = $em->find(Attribute::class, $leftFace->getId());
        $this->assertSame($alice->getId(), $leftFace->getValue());
        $this->assertSame(Attribute::ORIGIN_HUMAN, $leftFace->getOrigin());
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $leftFace->getOriginUserId());
        $this->assertSame(1.0, $leftFace->getConfidence());
        $this->assertNull($em->find(AttributeEntity::class, $unknownAlice->getId()), 'The orphan unknown person is removed');
        $this->assertNotNull($em->find(AttributeEntity::class, $unknownBob->getId()), 'The other unknown person stays');

        // 3. Another asset with a look-alike of Alice and a stranger: Alice is recognized, the stranger starts a new person
        $client->setFaces([
            self::face(self::BOX_LEFT, self::ALICE_LOOKALIKE),
            self::face(self::BOX_RIGHT, self::STRANGER),
        ]);
        $faces = $analyzer->analyze($otherPhoto, $config);
        $this->assertCount(2, $faces);
        [$lookalike, $strangerFace] = $faces;
        $this->assertSame($alice->getId(), $lookalike->getValue());
        $this->assertSame(Attribute::ORIGIN_MACHINE, $lookalike->getOrigin());
        $this->assertGreaterThanOrEqual(0.6, $lookalike->getConfidence());
        $this->assertLessThan(1.0, $lookalike->getConfidence());
        $unknownStranger = $this->person($strangerFace);
        $this->assertSame(AttributeEntity::STATUS_PENDING, $unknownStranger->getStatus());
        $this->assertNotSame($unknownBob->getId(), $unknownStranger->getId());

        // 4. Re-running the detection on the group photo keeps what the user said
        $client->setFaces([
            self::face(self::BOX_LEFT, self::ALICE),
            self::face(self::BOX_RIGHT, self::BOB),
        ]);
        $faces = $analyzer->analyze($groupPhoto, $config);
        $this->assertCount(2, $faces);
        $this->assertSame($alice->getId(), $faces[0]->getValue());
        $this->assertSame(Attribute::ORIGIN_HUMAN, $faces[0]->getOrigin());
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $faces[0]->getOriginUserId());
        $this->assertSame($unknownBob->getId(), $faces[1]->getValue(), 'The unknown face joins its pending person again');
        $this->assertCount(2, $this->getFaces($groupPhoto), 'Previous faces are replaced');
        $leftFace = $faces[0];

        // 5. A user names Bob: nobody else looks like him, nothing else changes
        $bob = $this->createEntity($this->list(), 'Bob');
        $this->api('PATCH', '/attributes/'.$faces[1]->getId(), KeycloakClientTestMock::ADMIN_UID, ['value' => $bob->getId()]);
        $this->assertResponseStatusCodeSame(200);
        $em->clear();
        $bobFace = $em->find(Attribute::class, $faces[1]->getId());
        $this->assertSame(0, $faceManager->propagate($bobFace, $config));
        $this->assertNull($em->find(AttributeEntity::class, $unknownBob->getId()));

        // 6. Alice gets a second look-alike recognized from her reference: propagation moves a
        //    face of a pending person, never a face a user named
        $strangerFace = $em->find(Attribute::class, $strangerFace->getId());
        $strangerFace->setEmbedding(['m' => 'mock', 'v' => self::ALICE_LOOKALIKE]);
        $em->flush();
        $leftFace = $em->find(Attribute::class, $leftFace->getId());
        $this->assertSame(1, $faceManager->propagate($leftFace, $config));
        $em->clear();
        $this->assertSame($alice->getId(), $em->find(Attribute::class, $strangerFace->getId())->getValue());
        $this->assertNull($em->find(AttributeEntity::class, $unknownStranger->getId()), 'The emptied unknown person is removed');
        $this->assertSame($bob->getId(), $em->find(Attribute::class, $bobFace->getId())->getValue(), 'Bob stays Bob');

        // 7. Homonyms: another Alice, told apart by her note, is a distinct person for the recognition
        $otherAlice = $this->createEntity($this->list(), 'Alice');
        $otherAlice->setNote('the photographer');
        $em->flush();
        $this->api('PATCH', '/attributes/'.$strangerFace->getId(), KeycloakClientTestMock::ADMIN_UID, ['value' => $otherAlice->getId()]);
        $this->assertResponseStatusCodeSame(200);
        $em->clear();
        $this->assertSame($otherAlice->getId(), $em->find(Attribute::class, $strangerFace->getId())->getValue());
        $this->assertSame(Attribute::ORIGIN_HUMAN, $em->find(Attribute::class, $strangerFace->getId())->getOrigin());

        // 8. Deleting a face through the API forgets it
        $this->api('DELETE', '/attributes/'.$strangerFace->getId(), KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(204);
        $em->clear();
        $this->assertCount(1, $this->getFaces($otherPhoto));

        // 9. Relinking from scratch: the look-alike still belongs to Alice, the named faces are untouched
        $this->assertSame(0, $faceManager->relink($config));
        $em->clear();
        $this->assertSame($alice->getId(), $em->find(Attribute::class, $lookalike->getId())->getValue());
        $this->assertSame(Attribute::ORIGIN_HUMAN, $em->find(Attribute::class, $bobFace->getId())->getOrigin());
    }

    public function testApprovingAnUnknownPersonMakesItsBestFaceAReference(): void
    {
        $em = self::getEntityManager();
        $this->setUpScene();
        $config = $this->createConfig(['matchThreshold' => 0.6]);
        $analyzer = self::getService(FaceRecognitionAnalyzer::class);
        /** @var FaceRecognitionClientMock $client */
        $client = self::getService(FaceRecognitionClient::class);

        $client->setFaces([self::face(self::BOX_LEFT, self::ALICE, 0.7)]);
        $first = $analyzer->analyze($this->createImageAsset(), $config)[0];
        $client->setFaces([self::face(self::BOX_LEFT, self::ALICE_LOOKALIKE, 0.9)]);
        $second = $analyzer->analyze($this->createImageAsset(), $config)[0];
        $this->assertSame($first->getValue(), $second->getValue(), 'Look-alikes share their pending person');
        $unknown = $this->person($first);
        $this->assertSame(AttributeEntity::STATUS_PENDING, $unknown->getStatus());

        // Pending persons are listed to the attribute editors of the workspace, so that they can name them
        $response = $this->api('GET', '/attribute-entities', KeycloakClientTestMock::ADMIN_UID, options: ['query' => ['list' => $this->list->getId(), 'status' => AttributeEntity::STATUS_PENDING, 'usage' => 1]]);
        $this->assertResponseStatusCodeSame(200);
        $member = $response->toArray()['member'];
        $this->assertCount(1, $member);
        $this->assertSame($unknown->getId(), $member[0]['id']);
        $this->assertSame(['attributes' => 2, 'humanAttributes' => 0], $member[0]['usage']);

        // Naming the person = renaming and approving the entity: its most confident face becomes the reference
        $this->api('PATCH', '/attribute-entities/'.$unknown->getId(), KeycloakClientTestMock::ADMIN_UID, ['value' => 'Alice', 'status' => AttributeEntity::STATUS_APPROVED]);
        $this->assertResponseStatusCodeSame(200);
        $em->clear();
        $this->assertSame('Alice', $em->find(AttributeEntity::class, $unknown->getId())->getValue());
        $this->assertSame(Attribute::ORIGIN_HUMAN, $em->find(Attribute::class, $second->getId())->getOrigin());
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $em->find(Attribute::class, $second->getId())->getOriginUserId());
        $this->assertSame(Attribute::ORIGIN_MACHINE, $em->find(Attribute::class, $first->getId())->getOrigin());

        // The faces of a person are listed through the attributes of the definition
        $response = $this->api('GET', '/attributes', KeycloakClientTestMock::ADMIN_UID, options: ['query' => ['definition' => $this->definition->getId(), 'value' => $unknown->getId(), 'origin' => 'human']]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([$second->getId()], array_column($response->toArray()['member'], 'id'));
    }

    public function testIncompleteIntegrationDoesNotBlockApprovingEntities(): void
    {
        $em = self::getEntityManager();
        $this->setUpScene();
        $integration = $this->createIntegration();
        $integration->setConfig([]);
        $em->flush();

        $entity = $this->createEntity($this->list, 'Alice', ['status' => AttributeEntity::STATUS_PENDING]);
        $this->api('PATCH', '/attribute-entities/'.$entity->getId(), KeycloakClientTestMock::ADMIN_UID, ['status' => AttributeEntity::STATUS_APPROVED]);
        $this->assertResponseStatusCodeSame(200);
        $em->clear();
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $em->find(AttributeEntity::class, $entity->getId())->getStatus());
    }

    public function testEveryFaceIsANewPersonWithoutThreshold(): void
    {
        $this->setUpScene();
        $config = $this->createConfig(['matchThreshold' => 0]);

        /** @var FaceRecognitionClientMock $client */
        $client = self::getService(FaceRecognitionClient::class);
        $client->setFaces([self::face(self::BOX_LEFT, self::ALICE), self::face(self::BOX_RIGHT, self::ALICE)]);
        $faces = self::getService(FaceRecognitionAnalyzer::class)->analyze($this->createImageAsset(), $config);
        $this->assertCount(2, $faces);
        $this->assertNotSame($faces[0]->getValue(), $faces[1]->getValue());
    }

    public function testAssetWithoutImageIsSkipped(): void
    {
        $this->setUpScene();
        $config = $this->createConfig();
        $this->assertInstanceOf(IntegrationConfig::class, $config);

        /** @var FaceRecognitionClientMock $client */
        $client = self::getService(FaceRecognitionClient::class);
        $calls = count($client->getCalls());

        $asset = $this->createAsset(['workspace' => $this->workspace]);
        $this->assertNull(self::getService(FaceRecognitionAnalyzer::class)->analyze($asset, $config));
        $this->assertCount($calls, $client->getCalls(), 'The service is not called');
    }

    public function testConfigurationIsValidated(): void
    {
        $this->setUpScene();
        $integrationManager = self::getService(IntegrationManager::class);

        $this->createAttributeDefinition(['workspace' => $this->workspace, 'name' => 'Names', 'slug' => 'names', 'multiple' => true]);
        $this->createAttributeDefinition(['workspace' => $this->workspace, 'name' => 'Single', 'slug' => 'single', 'type' => EntityAttributeType::NAME, 'list' => $this->list]);
        $this->createAttributeDefinition(['workspace' => $this->workspace, 'name' => 'Listless', 'slug' => 'listless', 'multiple' => true, 'type' => EntityAttributeType::NAME]);

        $integrationManager->validateIntegration($this->createIntegration());

        foreach ([
            ['attribute' => 'names'],
            ['attribute' => 'single'],
            ['attribute' => 'listless'],
            ['attribute' => 'nope'],
        ] as $invalid) {
            try {
                $integrationManager->validateIntegration($this->createIntegration($invalid));
                $this->fail('Expected an invalid configuration for '.json_encode($invalid));
            } catch (InvalidConfigurationException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function setUpScene(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->list = $this->createEntityList(['name' => 'Persons', 'workspace' => $this->workspace]);
        $this->definition = $this->createAttributeDefinition([
            'workspace' => $this->workspace,
            'name' => 'Persons',
            'slug' => 'persons',
            'multiple' => true,
            'type' => EntityAttributeType::NAME,
            'list' => $this->list,
        ]);
    }

    /**
     * The list, reattached after the entity manager was cleared.
     */
    private function list(): EntityList
    {
        $em = self::getEntityManager();
        if (!$em->contains($this->list)) {
            $this->list = $em->find(EntityList::class, $this->list->getId());
        }

        return $this->list;
    }

    private function createConfig(array $config = []): IntegrationConfig
    {
        return self::getService(IntegrationManager::class)->getIntegrationConfiguration($this->createIntegration($config));
    }

    private function createIntegration(array $config = []): WorkspaceIntegration
    {
        $em = self::getEntityManager();

        $integration = new WorkspaceIntegration();
        $integration->setPublic(true);
        $integration->setWorkspace($this->workspace);
        $integration->setName('Faces '.uniqid());
        $integration->setIntegration(FaceRecognitionIntegration::getName());
        $integration->setConfig(array_merge([
            'attribute' => 'persons',
        ], $config));
        $integration->setOwnerId('custom_owner');
        $em->persist($integration);
        $em->flush();

        return $integration;
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

    private static function boxOf(Attribute $face): array
    {
        return array_map('floatval', array_intersect_key(FaceRecognitionAnalyzer::boxOf($face) ?? [], array_flip(['x', 'y', 'w', 'h'])));
    }

    private function person(Attribute $face): AttributeEntity
    {
        $entity = self::getEntityManager()->find(AttributeEntity::class, $face->getValue());
        $this->assertInstanceOf(AttributeEntity::class, $entity);

        return $entity;
    }

    private function createImageAsset(): Asset
    {
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace($this->workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPathPublic(true);
        $file->setPath('test/'.uniqid().'.png');
        $file->setType('image/png');
        // Short-circuits the storage fetch
        $file->localTmpPath = __DIR__.'/../../../../fixtures/files/alchemy.png';
        $em->persist($file);

        $asset = $this->createAsset([
            'workspace' => $this->workspace,
            'no_flush' => true,
        ]);
        $asset->setSource($file);
        $em->flush();

        return $asset;
    }

    /**
     * @return Attribute[]
     */
    private function getFaces(Asset $asset): array
    {
        return self::getService(AttributeRepository::class)->findEmbeddingsOfAsset($this->definition->getId(), $asset->getId());
    }
}
