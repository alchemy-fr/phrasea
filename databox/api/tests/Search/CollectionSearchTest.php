<?php

declare(strict_types=1);

namespace App\Tests\Search;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\WorkspaceItemPrivacyInterface;

class CollectionSearchTest extends AbstractSearchTest
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('collection');
    }

    public function testSearchRootWithCollectionsInNonPublicWorkspaceAsAnonymousUser(): void
    {
        $A = $this->createCollection([
            'name' => 'A',
        ]);
        $this->createCollection([
            'name' => 'B',
            'parent' => $A,
            'public' => true,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');
        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(0, $data);
    }

    public function testSearchRootWithNonPublicCollectionsInPublicWorkspaceAsAnonymousUser(): void
    {
        $workspace = $this->createWorkspace([
            'name' => 'Workspace',
            'public' => true,
        ]);
        $A = $this->createCollection([
            'name' => 'A',
            'workspace' => $workspace,
        ]);
        $this->createCollection([
            'name' => 'B',
            'parent' => $A,
            'workspace' => $workspace,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(0, $data);
    }

    public function testSearchRootWithOnePublicSubCollectionInPublicWorkspaceAsAnonymousUser(): void
    {
        $workspace = $this->createWorkspace([
            'name' => 'Workspace',
            'public' => true,
            'no_flush' => true,
        ]);
        $A = $this->createCollection([
            'name' => 'A',
            'workspace' => $workspace,
            'no_flush' => true,
        ]);
        $collB = $this->createCollection([
            'name' => 'B',
            'parent' => $A,
            'public' => true,
            'workspace' => $workspace,
            'no_flush' => true,
        ]);
        $this->createCollectionAccess($collB, null, WorkspaceItemPrivacyInterface::PUBLIC);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertSame('B', $data[0]['name']);
    }

    public function testSearchRootWithTwoPublicSubCollectionsInPublicWorkspaceAsAnonymousUser(): void
    {
        $workspace = $this->createWorkspace([
            'name' => 'Workspace',
            'public' => true,
            'no_flush' => true,
        ]);
        $A = $this->createCollection([
            'name' => 'A',
            'public' => true,
            'workspace' => $workspace,
            'no_flush' => true,
        ]);
        $this->createCollection([
            'name' => 'B',
            'parent' => $A,
            'public' => true,
            'workspace' => $workspace,
            'no_flush' => true,
        ]);
        $this->createCollectionAccess($A, null, WorkspaceItemPrivacyInterface::PUBLIC);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertSame('A', $data[0]['name']);
    }

    public function testSearchPublicCollectionsInPrivateWorkspaceAsAnonymousUser(): void
    {
        $this->createCollection([
            'name' => 'Foo',
            'public' => true,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(0, $data);
    }

    public function testSearchPublicCollectionsInPublicWorkspaceAsAnonymousUser(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Foo',
            'public' => true,
            'no_flush' => true,
        ]);
        $this->createCollectionAccess($collection, null, WorkspaceItemPrivacyInterface::PUBLIC);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertEquals($collection->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    public function testSearchNonPublicCollectionsAsAnonymousUser(): void
    {
        $this->createCollection([
            'name' => 'Foo',
            'public' => false,
            'ownerId' => 'OWNER',
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEmpty($data);
    }

    public function testSearchOwnedCollectionsAsOwner(): void
    {
        $collection = $this->createCollection([
            'name' => 'Foo',
            'ownerId' => KeycloakClientTestMock::USER_UID,
            'no_flush' => true,
        ]);
        $this->createCollectionAccess($collection, KeycloakClientTestMock::USER_UID, WorkspaceItemPrivacyInterface::PRIVATE);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $this->defaultWorkspace->getId());

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertEquals($collection->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    public function testSearchOwnedCollectionsAsOwnerButNotAllowedToWorkspace(): void
    {
        $this->createCollection([
            'name' => 'Foo',
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(0, $data);
    }

    public function testSearchNonOwnedCollectionsAsOwner(): void
    {
        $this->createAsset([
            'name' => 'Foo',
            'ownerId' => 'another_owner',
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEmpty($data);
    }

    public function testSearchCollectionsWithACEOnCollection(): void
    {
        $collection = $this->createCollection([
            'name' => 'Foo',
            'ownerId' => 'another_owner',
        ]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $this->defaultWorkspace->getId());

        self::releaseIndex();

        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $collection,
            PermissionInterface::VIEW
        );
        $this->createCollectionAccess($collection, KeycloakClientTestMock::USER_UID, WorkspaceItemPrivacyInterface::PRIVATE);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertEquals($collection->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    public function testSearchCollectionsWithACEOnAllCollections(): void
    {
        $collection = $this->createCollection([
            'name' => 'Foo',
            'ownerId' => 'another_owner',
        ]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $this->defaultWorkspace->getId());
        $this->createCollectionAccess($collection, KeycloakClientTestMock::USER_UID, WorkspaceItemPrivacyInterface::PRIVATE);
        self::releaseIndex();

        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            KeycloakClientTestMock::USER_UID,
            'collection',
            null,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertEquals($collection->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }
}
