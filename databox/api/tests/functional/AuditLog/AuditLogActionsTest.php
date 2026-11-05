<?php

declare(strict_types=1);

namespace App\Tests\Functional\AuditLog;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Audit\AuditContext;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Consumer\Handler\Asset\AssetsRestore;
use App\Consumer\Handler\Collection\CollectionsMoveToTrash;
use App\Consumer\Handler\Collection\CollectionsRestore;
use App\Consumer\Stamp\AuditContextStamp;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * The actions logged explicitly: DQL/SQL writes, permissions, downloads, cascades, asynchronous handlers.
 */
class AuditLogActionsTest extends AbstractDataboxTestCase
{
    use AuditLogTestTrait;
    use RenditionTestTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string CORRELATION_ID = 'c0ffee00-0000-4000-8000-000000000000';

    public function testAssetTrashAndRestoreInAWorkerKeepTheActor(): void
    {
        $asset = $this->createAsset(['name' => 'To trash']);
        $this->purgeLogs();

        $this->consume(new AssetsDelete([$asset->getId()]));

        $trashed = $this->findLog(AuditLogActionEnum::ObjectTrashed, $asset->getId());
        $this->assertSame(AuditActorTypeEnum::User, $trashed->getActorType());
        $this->assertSame(self::USER, $trashed->getUserId());
        $this->assertSame('alice', $trashed->getUserName());
        $this->assertSame(self::CORRELATION_ID, $trashed->getCorrelationId());
        $this->assertSame(AuditContext::SOURCE_WORKER, $trashed->getMeta()['source']);
        $this->assertSame('/assets', $trashed->getMeta()['uri']);

        $this->consume(new AssetsRestore([$asset->getId()]));
        $restored = $this->findLog(AuditLogActionEnum::ObjectRestored, $asset->getId());
        $this->assertSame(self::USER, $restored->getUserId());
    }

    public function testCollectionTrashAndRestoreWrittenInDql(): void
    {
        $collection = $this->createCollection(['name' => 'Archives']);
        $this->purgeLogs();

        $this->consume(new CollectionsMoveToTrash([$collection->getId()]));
        $trashed = $this->findLog(AuditLogActionEnum::ObjectTrashed, $collection->getId());
        $this->assertSame('Archives', $trashed->getObjectLabel());
        $this->assertSame(self::USER, $trashed->getUserId());

        $this->consume(new CollectionsRestore([$collection->getId()]));
        $this->findLog(AuditLogActionEnum::ObjectRestored, $collection->getId());
    }

    public function testAttributeBatchLogsOldAndNewValuesOncePerAsset(): void
    {
        $title = $this->createAttributeDefinition(['name' => 'Title']);
        $keywords = $this->createAttributeDefinition(['name' => 'Keywords', 'multiple' => true]);
        $a1 = $this->createAsset([
            'attributes' => [
                ['definition' => $title, 'value' => 'Blue sea'],
                ['definition' => $keywords, 'value' => 'sea'],
            ],
        ]);
        $a2 = $this->createAsset([
            'attributes' => [
                ['definition' => $title, 'value' => 'Blue sky'],
            ],
        ]);
        $this->purgeLogs();

        $input = new AssetAttributeBatchUpdateInput();
        $input->actions = [
            self::action(BatchAttributeManager::ACTION_REPLACE, $title->getId(), 'Blue', ['replaceWith' => 'Red']),
            self::action(BatchAttributeManager::ACTION_ADD, $keywords->getId(), 'wave', ['assets' => [$a1->getId()]]),
            self::action(BatchAttributeManager::ACTION_DELETE, $keywords->getId(), null, ['assets' => [$a2->getId()]]),
        ];

        self::getService(BatchAttributeManager::class)->handleBatch(
            $a1->getWorkspaceId(),
            [$a1->getId(), $a2->getId()],
            $input,
            null,
        );

        $log = $this->findLog(AuditLogActionEnum::AssetAttributeChanged, $a1->getId());
        $changes = array_column($log->getData()['changes'], null, 'definitionName');
        $this->assertSame('replace', $changes['Title']['op']);
        $this->assertSame(['Blue sea'], $changes['Title']['old']);
        $this->assertSame(['Red sea'], $changes['Title']['new']);
        $this->assertSame('add', $changes['Keywords']['op']);
        $this->assertSame(['sea'], $changes['Keywords']['old']);
        $this->assertEqualsCanonicalizing(['sea', 'wave'], $changes['Keywords']['new']);

        $log = $this->findLog(AuditLogActionEnum::AssetAttributeChanged, $a2->getId());
        $this->assertCount(1, $log->getData()['changes'], 'The deletion of no value changes nothing');
        $this->assertSame(['Red sky'], $log->getData()['changes'][0]['new']);
    }

    public function testAttributeSetAndDeleteInABatch(): void
    {
        $title = $this->createAttributeDefinition(['name' => 'Title']);
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $title, 'value' => 'Before'],
            ],
        ]);
        $this->purgeLogs();

        $manager = self::getService(BatchAttributeManager::class);
        $input = new AssetAttributeBatchUpdateInput();
        $input->actions = [self::action(BatchAttributeManager::ACTION_SET, $title->getId(), 'After')];
        $manager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input, null);

        $change = $this->findLog(AuditLogActionEnum::AssetAttributeChanged, $asset->getId())->getData()['changes'][0];
        $this->assertSame(['set', ['Before'], ['After']], [$change['op'], $change['old'], $change['new']]);

        $this->purgeLogs();
        $input->actions = [self::action(BatchAttributeManager::ACTION_DELETE, $title->getId(), null)];
        $manager->handleBatch($asset->getWorkspaceId(), [$asset->getId()], $input, null);

        $change = $this->findLog(AuditLogActionEnum::AssetAttributeChanged, $asset->getId())->getData()['changes'][0];
        $this->assertSame(['delete', ['After'], []], [$change['op'], $change['old'], $change['new']]);
    }

    public function testPermissionsGrantedChangedAndRevoked(): void
    {
        $collection = $this->createCollection(['name' => 'Shared']);
        $this->purgeLogs();
        $permissionManager = self::getPermissionManager();

        $permissionManager->updateOrCreateAce(AccessControlEntryInterface::TYPE_USER_VALUE, self::USER, Collection::OBJECT_TYPE, $collection->getId(), PermissionInterface::VIEW);
        $granted = $this->findLog(AuditLogActionEnum::PermissionGranted, $collection->getId());
        $this->assertSame(Collection::OBJECT_INDEX, $granted->getObjectType());
        $this->assertSame('Shared', $granted->getObjectLabel());
        $this->assertSame('user', $granted->getData()['userType']);
        $this->assertSame(self::USER, $granted->getData()['userId']);
        $this->assertSame(['VIEW'], $granted->getData()['permissions']);
        $this->assertNull($granted->getData()['previousMask']);

        $permissionManager->updateOrCreateAce(AccessControlEntryInterface::TYPE_USER_VALUE, self::USER, Collection::OBJECT_TYPE, $collection->getId(), PermissionInterface::VIEW | PermissionInterface::EDIT);
        $changed = $this->findLog(AuditLogActionEnum::PermissionChanged, $collection->getId());
        $this->assertSame(['VIEW'], $changed->getData()['previousPermissions']);
        $this->assertSame(['VIEW', 'EDIT'], $changed->getData()['permissions']);

        $permissionManager->deleteAce(AccessControlEntryInterface::TYPE_USER_VALUE, self::USER, Collection::OBJECT_TYPE, $collection->getId());
        $revoked = $this->findLog(AuditLogActionEnum::PermissionRevoked, $collection->getId());
        $this->assertSame(['VIEW', 'EDIT'], $revoked->getData()['previousPermissions']);
        $this->assertSame([], $revoked->getData()['permissions']);

        // An ACE on the type has no object ID
        $permissionManager->updateOrCreateAce(AccessControlEntryInterface::TYPE_USER_VALUE, self::USER, Collection::OBJECT_TYPE, null, PermissionInterface::VIEW);
        $logs = array_filter($this->findLogs(AuditLogActionEnum::PermissionGranted), static fn ($log): bool => null === $log->getObjectId());
        $this->assertCount(1, $logs);
        $this->assertSame(Collection::OBJECT_INDEX, array_values($logs)[0]->getObjectType());
    }

    public function testExportLogsADownloadPerAsset(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('ws-export', self::USER);
        $asset = $this->createAsset(['workspace' => $workspace, 'ownerId' => self::USER, 'name' => 'Exported']);
        $main = $defaults->renditionDefinitions['main'];
        $this->createAssetRendition($asset, $main, $this->createUrlFile($workspace));
        $this->purgeLogs();

        $response = $this->jsonRequest('POST', '/asset-exports', self::USER, [
            'assets' => [$asset->getId()],
            'renditions' => [$main->getId()],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $log = $this->findLog(AuditLogActionEnum::AssetDownloaded, $asset->getId());
        $this->assertSame($response->toArray()['id'], $log->getData()['exportId']);
        $this->assertSame([$main->getId()], $log->getData()['renditions']);
        $this->assertSame(self::USER, $log->getUserId());
    }

    public function testWorkspaceDeletionIsASingleEntry(): void
    {
        $workspace = $this->createWorkspace(['name' => 'Doomed', 'slug' => 'doomed', 'ownerId' => self::USER]);
        $workspaceId = $workspace->getId();
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Col']);
        $this->createAsset(['workspace' => $workspace, 'collectionId' => $collection->getId()]);
        $this->createAsset(['workspace' => $workspace]);
        $this->purgeLogs();

        static::createClient()->request('DELETE', '/workspaces/'.$workspaceId, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(self::USER)],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->findLog(AuditLogActionEnum::ObjectTrashed, $workspaceId);
        $deleted = $this->findLog(AuditLogActionEnum::ObjectDeleted, $workspaceId);
        $this->assertSame(Workspace::OBJECT_INDEX, $deleted->getObjectType());
        $this->assertSame('Doomed', $deleted->getObjectLabel());
        $this->assertSame(self::USER, $deleted->getUserId());
        $this->assertSame(2, $deleted->getData()['purged']['asset']);
        $this->assertSame(1, $deleted->getData()['purged']['collection']);
        $this->assertArrayNotHasKey('workspace', $deleted->getData()['purged']);

        // Nothing else logged for the cascade
        $this->assertCount(1, $this->findLogs(AuditLogActionEnum::ObjectDeleted));
    }

    public function testHardDeletedAssetsAreOneEntryEach(): void
    {
        $title = $this->createAttributeDefinition(['name' => 'Title', 'no_flush' => true]);
        $title->setNamePriority(1);
        self::getEntityManager()->flush();
        $this->assertSame(1, self::getEntityManager()->getRepository(\App\Entity\Core\AttributeDefinition::class)->find($title->getId())?->getNamePriority());
        $asset = $this->createAsset([
            'attributes' => [
                ['definition' => $title, 'value' => 'Gone'],
            ],
        ]);
        $this->purgeLogs();

        $this->consume(new AssetsDelete([$asset->getId()], hardDelete: true));

        $log = $this->findLog(AuditLogActionEnum::ObjectDeleted, $asset->getId());
        $this->assertSame(Asset::OBJECT_INDEX, $log->getObjectType());
        $this->assertSame('Gone', $log->getObjectLabel());
        $this->assertCount(1, $this->findLogs());
    }

    private static function action(string $action, string $definitionId, mixed $value, array $options = []): AttributeActionInput
    {
        $input = new AttributeActionInput();
        $input->action = $action;
        $input->definitionId = $definitionId;
        $input->value = $value;
        $input->assets = $options['assets'] ?? null;
        $input->replaceWith = $options['replaceWith'] ?? null;

        return $input;
    }

    /**
     * Handles a message as a worker would, with the audit context of the dispatching request.
     */
    private function consume(object $message): void
    {
        self::getService(MessageBusInterface::class)->dispatch(new Envelope($message, [
            new AuditContextStamp([
                'actorType' => AuditActorTypeEnum::User->value,
                'userId' => self::USER,
                'userName' => 'alice',
                'impersonatorId' => null,
                'clientId' => null,
                'ip' => '10.0.0.1',
                'uri' => '/assets',
                'route' => null,
                'source' => AuditContext::SOURCE_API,
                'command' => null,
                'correlationId' => self::CORRELATION_ID,
            ]),
            new ReceivedStamp('p1'),
            new ConsumedByWorkerStamp(),
        ]));
    }
}
