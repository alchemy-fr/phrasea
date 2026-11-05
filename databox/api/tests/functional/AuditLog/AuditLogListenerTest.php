<?php

declare(strict_types=1);

namespace App\Tests\Functional\AuditLog;

use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Audit\AuditIgnore;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceSecret;
use App\Entity\Log\AuditLog;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * The changes the UnitOfWork sees, logged by the AuditLogListener.
 */
class AuditLogListenerTest extends AbstractDataboxTestCase
{
    use AuditLogTestTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    public function testCollectionIsLoggedWhenCreatedUpdatedAndDeleted(): void
    {
        $this->authenticate(new JwtUser('jwt', self::USER, 'alice'));
        $collection = $this->createCollection(['name' => 'Holidays']);
        $collectionId = $collection->getId();

        $created = $this->findLog(AuditLogActionEnum::ObjectCreated, $collectionId);
        $this->assertSame(Collection::OBJECT_INDEX, $created->getObjectType());
        $this->assertSame('Holidays', $created->getObjectLabel());
        $this->assertSame($collection->getWorkspaceId(), $created->getWorkspaceId());
        $this->assertSame(AuditActorTypeEnum::User, $created->getActorType());
        $this->assertSame(self::USER, $created->getUserId());
        $this->assertSame('alice', $created->getUserName());
        $this->assertSame('Holidays', $created->getData()['values']['name']);
        $this->assertArrayNotHasKey('createdAt', $created->getData()['values']);
        $this->assertNotNull($created->getCorrelationId());

        $em = self::getEntityManager();
        $collection = $em->find(Collection::class, $collectionId);
        $collection->setName('Summer holidays');
        $em->flush();

        $updated = $this->findLog(AuditLogActionEnum::ObjectUpdated, $collectionId);
        $this->assertSame(['name' => ['Holidays', 'Summer holidays']], $updated->getData()['changes']);
        $this->assertSame('Summer holidays', $updated->getObjectLabel());

        // A change of an ignored field only is not logged
        $collection = $em->find(Collection::class, $collectionId);
        new \ReflectionProperty(Collection::class, 'updatedAt')->setValue($collection, new \DateTimeImmutable('+1 hour'));
        $em->flush();
        $this->assertCount(1, $this->findLogs(AuditLogActionEnum::ObjectUpdated, $collectionId));

        // Removing a collection moves it to the trash
        $em->remove($em->find(Collection::class, $collectionId));
        $em->flush();
        $this->findLog(AuditLogActionEnum::ObjectTrashed, $collectionId);
    }

    public function testCollectionMoveIsADedicatedAction(): void
    {
        $parent = $this->createCollection(['name' => 'Parent']);
        $child = $this->createCollection(['name' => 'Child']);
        $this->purgeLogs();

        $em = self::getEntityManager();
        $child = $em->find(Collection::class, $child->getId());
        $child->setParent($em->find(Collection::class, $parent->getId()));
        $em->flush();

        $log = $this->findLog(AuditLogActionEnum::CollectionMoved, $child->getId());
        $this->assertSame(['oldParentId' => null, 'newParentId' => $parent->getId()], $log->getData());
        $this->assertCount(0, $this->findLogs(AuditLogActionEnum::ObjectUpdated, $child->getId()));
    }

    public function testSecretValueIsMasked(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $em = self::getEntityManager();

        $secret = new WorkspaceSecret();
        $secret->setWorkspace($workspace);
        $secret->setName('API_KEY');
        $secret->setValue('s3cr3t');
        $em->persist($secret);
        $em->flush();

        $created = $this->findLog(AuditLogActionEnum::ObjectCreated, $secret->getId());
        $this->assertSame(AuditIgnore::MASK, $created->getData()['values']['value']);

        $secret = $em->find(WorkspaceSecret::class, $secret->getId());
        $secret->setValue('n3w-s3cr3t');
        $em->flush();

        $updated = $this->findLog(AuditLogActionEnum::ObjectUpdated, $secret->getId());
        $this->assertSame(['value' => [AuditIgnore::MASK, AuditIgnore::MASK]], $updated->getData()['changes']);
        $this->assertStringNotContainsString('s3cr3t', json_encode([$created->getData(), $updated->getData()], JSON_THROW_ON_ERROR));
    }

    public function testAssetMoveAndSubstitutionAreDedicatedActions(): void
    {
        $from = $this->createCollection(['name' => 'From']);
        $to = $this->createCollection(['name' => 'To']);
        $asset = $this->createAsset(['name' => 'Sunset', 'collectionId' => $from->getId()]);
        $assetId = $asset->getId();

        $created = $this->findLog(AuditLogActionEnum::ObjectCreated, $assetId);
        $this->assertSame('Sunset', $created->getObjectLabel());
        // Covered by the creation, which has the reference collection
        $this->assertCount(0, $this->findLogs(AuditLogActionEnum::AssetAddedToCollection, $assetId));

        $em = self::getEntityManager();
        $asset = $this->findAsset($assetId);
        $asset->setReferenceCollection($em->find(Collection::class, $to->getId()));
        $em->flush();

        $moved = $this->findLog(AuditLogActionEnum::AssetMoved, $assetId);
        $this->assertSame([
            'oldCollectionId' => $from->getId(),
            'newCollectionId' => $to->getId(),
        ], $moved->getData());
        $this->assertCount(0, $this->findLogs(AuditLogActionEnum::ObjectUpdated, $assetId));

        $file = new File();
        $file->setWorkspace($em->find(Workspace::class, $asset->getWorkspaceId()));
        $file->setStorage(File::STORAGE_URL);
        $file->setPath('https://cdn.example.com/new.jpg');
        $file->setType('image/jpeg');
        $em->persist($file);
        $asset = $this->findAsset($assetId);
        $asset->setSource($file);
        $em->flush();

        $substituted = $this->findLog(AuditLogActionEnum::AssetSubstituted, $assetId);
        $this->assertNull($substituted->getData()['oldSourceId']);
        $this->assertSame($file->getId(), $substituted->getData()['newSourceId']);
    }

    public function testAssetAddedToAndRemovedFromACollection(): void
    {
        $collection = $this->createCollection(['name' => 'Selection']);
        $asset = $this->createAsset();
        $this->purgeLogs();

        $relationId = $this->addAssetToCollection($collection->getId(), $asset->getId());

        $added = $this->findLog(AuditLogActionEnum::AssetAddedToCollection, $asset->getId());
        $this->assertSame([
            'collectionId' => $collection->getId(),
            'collectionTitle' => 'Selection',
        ], $added->getData());

        $em = self::getEntityManager();
        $em->remove($em->find(\App\Entity\Core\CollectionAsset::class, $relationId));
        $em->flush();

        $removed = $this->findLog(AuditLogActionEnum::AssetRemovedFromCollection, $asset->getId());
        $this->assertSame($collection->getId(), $removed->getData()['collectionId']);
    }

    public function testAttributeChangesAreRolledUpPerAsset(): void
    {
        $title = $this->createAttributeDefinition(['name' => 'Title']);
        $keywords = $this->createAttributeDefinition(['name' => 'Keywords', 'multiple' => true]);
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $title, 'value' => 'Old title'],
                ['definition' => $keywords, 'value' => 'sea'],
            ],
        ]);
        $this->purgeLogs();

        $em = self::getEntityManager();
        $asset = $this->findAsset($asset->getId());
        foreach ($asset->getAttributes() as $attribute) {
            if ($attribute->getDefinition()->getId() === $title->getId()) {
                $attribute->setValue('New title');
            } else {
                $em->remove($attribute);
            }
        }
        $em->flush();

        $log = $this->findLog(AuditLogActionEnum::AssetAttributeChanged, $asset->getId());
        $changes = array_column($log->getData()['changes'], null, 'definitionName');
        $this->assertSame('set', $changes['Title']['op']);
        $this->assertSame(['Old title'], $changes['Title']['old']);
        $this->assertSame(['New title'], $changes['Title']['new']);
        $this->assertSame('delete', $changes['Keywords']['op']);
        $this->assertSame(['sea'], $changes['Keywords']['old']);
        $this->assertSame([], $changes['Keywords']['new']);
    }

    public function testOAuthClientIsTheActor(): void
    {
        $this->authenticate(new JwtOauthClient('jwt', 'machine_client', ['collection:create']));
        $collection = $this->createCollection(['name' => 'From a client']);

        $log = $this->findLog(AuditLogActionEnum::ObjectCreated, $collection->getId());
        $this->assertSame(AuditActorTypeEnum::OAuthClient, $log->getActorType());
        $this->assertSame('machine_client', $log->getClientId());
        $this->assertNull($log->getUserId());
    }

    public function testImpersonatorIsLogged(): void
    {
        self::enableFixtures();
        static::createClient()->request('POST', '/baskets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::ADMIN),
                \Alchemy\AuthBundle\Security\Impersonator::HEADER => self::USER,
            ],
            'json' => [
                'name' => 'Impersonated basket',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $logs = array_filter(
            $this->findLogs(AuditLogActionEnum::ObjectCreated),
            static fn (AuditLog $log): bool => 'Impersonated basket' === $log->getObjectLabel(),
        );
        $this->assertCount(1, $logs);
        $log = array_values($logs)[0];
        $this->assertSame(self::USER, $log->getUserId());
        $this->assertSame(self::ADMIN, $log->getImpersonatorId());
        $this->assertSame('/baskets', $log->getMeta()['uri']);
        $this->assertSame('api', $log->getMeta()['source']);
    }
}
