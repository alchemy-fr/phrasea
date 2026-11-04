<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Consumer\Handler\File\DeleteFileFromStorage;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetImport;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * An import is created by whoever can create assets in its destination, and read by its
 * owner or by whoever can create assets in its workspace.
 */
final class AssetImportApiTest extends AbstractDataboxTestCase
{
    use AssetImportTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interceptQueues();
    }

    public function testCreatingAnImportRequiresTheAssetCreationRight(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $collection = $this->createCollection(['workspace' => $workspace, 'ownerId' => self::OWNER]);
        $data = self::ndjson([['name' => 'a']]);

        $this->postImport($workspace, $data, userId: self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->postImport($workspace, $data, ['collection' => '/collections/'.$collection->getId()], self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->grantUserOnObject(self::OTHER, $collection, PermissionInterface::CHILD_CREATE);
        $this->postImport($workspace, $data, ['collection' => '/collections/'.$collection->getId()], self::OTHER);
        $this->assertResponseStatusCodeSame(201);

        $this->apiRequest('POST', '/asset-imports', null, ['workspace' => '/workspaces/'.$workspace->getId(), 'format' => 'ndjson', 'data' => $data]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testInvalidCreations(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        [$otherWorkspace] = $this->createWorkspaceWithDefaults('ws-other', self::OWNER);
        $otherCollection = $this->createCollection(['workspace' => $otherWorkspace, 'ownerId' => self::OWNER]);

        $this->apiRequest('POST', '/asset-imports', self::OWNER, ['workspace' => '/workspaces/'.$workspace->getId(), 'format' => 'ndjson']);
        $this->assertResponseStatusCodeSame(400, 'neither "data" nor "multipart"');

        $this->postImport($workspace, '   ');
        $this->assertResponseStatusCodeSame(400, 'empty data');

        $this->postImport($workspace, '{}', ['format' => 'csv']);
        $this->assertResponseStatusCodeSame(422, 'unknown format');

        $this->postImport($workspace, '{}', ['privacy' => 42]);
        $this->assertResponseStatusCodeSame(400, 'unknown privacy');

        $this->postImport($workspace, '{}', ['collection' => '/collections/'.$otherCollection->getId()]);
        $this->assertResponseStatusCodeSame(400, 'collection of another workspace');

        $this->assertSame([], self::getEntityManager()->getRepository(AssetImport::class)->findAll());
    }

    public function testAnImportIsReadByItsOwnerAndTheWorkspaceContributors(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());

        $importId = $this->postImport($workspace, self::ndjson([['name' => 'a'], ['key' => 'invalid']]))->toArray()['id'];
        $this->consumeImportMessages();

        $data = $this->apiRequest('GET', '/asset-imports/'.$importId, self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame('completed', $data['status']);
        $this->assertSame([2, 1, 0, 1], [$data['total'], $data['created'], $data['skipped'], $data['failed']]);
        $this->assertSame($workspace->getId(), $data['workspaceId']);

        $errors = $this->apiRequest('GET', '/asset-imports/'.$importId.'/errors', self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $errors['totalItems']);
        $this->assertSame(2, $errors['member'][0]['line']);
        $this->assertSame('invalid', $errors['member'][0]['key']);
        $this->assertArrayHasKey('message', $errors['member'][0]);

        $list = $this->apiRequest('GET', '/asset-imports?workspace='.$workspace->getId(), self::OWNER)->toArray();
        $this->assertSame([$importId], array_column($list['member'], 'id'));

        // A member of the workspace who cannot create assets
        $this->apiRequest('GET', '/asset-imports/'.$importId, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->apiRequest('GET', '/asset-imports/'.$importId.'/errors', self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->apiRequest('DELETE', '/asset-imports/'.$importId, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $list = $this->apiRequest('GET', '/asset-imports?workspace='.$workspace->getId(), self::OTHER)->toArray();
        $this->assertSame(0, $list['totalItems']);

        // Once allowed to create assets in the workspace
        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);
        $this->apiRequest('GET', '/asset-imports/'.$importId, self::OTHER);
        $this->assertResponseIsSuccessful();
        $list = $this->apiRequest('GET', '/asset-imports?workspace='.$workspace->getId(), self::OTHER)->toArray();
        $this->assertSame([$importId], array_column($list['member'], 'id'));

        $this->apiRequest('GET', '/asset-imports', self::OWNER);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testTheListShowsTheOwnImportsToWhoCannotCreateInTheWholeWorkspace(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $collection = $this->createCollection(['workspace' => $workspace, 'ownerId' => self::OWNER]);
        $this->grantUserOnObject(self::OTHER, $collection, PermissionInterface::CHILD_CREATE);

        $ownerImportId = $this->postImport($workspace, self::ndjson([['name' => 'a']]))->toArray()['id'];
        $ownImportId = $this->postImport($workspace, self::ndjson([['name' => 'b']]), ['collection' => '/collections/'.$collection->getId()], self::OTHER)->toArray()['id'];

        $list = $this->apiRequest('GET', '/asset-imports?workspace='.$workspace->getId(), self::OTHER)->toArray();
        $this->assertSame([$ownImportId], array_column($list['member'], 'id'));

        $list = $this->apiRequest('GET', '/asset-imports?workspace='.$workspace->getId(), self::OWNER)->toArray();
        $this->assertEqualsCanonicalizing([$ownerImportId, $ownImportId], array_column($list['member'], 'id'));
    }

    public function testDeletingAnImportDeletesItsFileButNotItsAssets(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);

        $importId = $this->postImport($workspace, self::ndjson([['name' => 'a']]))->toArray()['id'];
        $this->consumeImportMessages();
        $import = $this->findImport($importId);
        $sourcePath = $import->getSourcePath();
        $this->assertTrue(self::getService(FileStorageManager::class)->has($sourcePath));

        $this->apiRequest('DELETE', '/asset-imports/'.$importId, self::OWNER);
        $this->assertResponseStatusCodeSame(204);

        $this->assertNull(self::getEntityManager()->find(AssetImport::class, $importId));
        $deletions = $this->getQueuedMessages($this->p2Queue, DeleteFileFromStorage::class);
        $this->assertSame([[$sourcePath]], array_map(static fn (DeleteFileFromStorage $message): array => $message->getPaths(), $deletions));
        $this->assertCount(1, self::getEntityManager()->getRepository(Asset::class)->findBy(['workspace' => $workspace->getId()]));
    }
}
