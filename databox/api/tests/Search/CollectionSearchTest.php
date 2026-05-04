<?php

declare(strict_types=1);

namespace App\Tests\Search;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

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
            'title' => 'A',
        ]);
        $this->createCollection([
            'title' => 'B',
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
            'title' => 'Workspace',
            'public' => true,
        ]);
        $A = $this->createCollection([
            'title' => 'A',
            'workspace' => $workspace,
        ]);
        $this->createCollection([
            'title' => 'B',
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
            'title' => 'Workspace',
            'public' => true,
        ]);
        $A = $this->createCollection([
            'title' => 'A',
            'workspace' => $workspace,
        ]);
        $this->createCollection([
            'title' => 'B',
            'parent' => $A,
            'public' => true,
            'workspace' => $workspace,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertSame('B', $data[0]['title']);
    }

    public function testSearchRootWithTwoPublicSubCollectionsInPublicWorkspaceAsAnonymousUser(): void
    {
        $workspace = $this->createWorkspace([
            'title' => 'Workspace',
            'public' => true,
        ]);
        $A = $this->createCollection([
            'title' => 'A',
            'public' => true,
            'workspace' => $workspace,
        ]);
        $this->createCollection([
            'title' => 'B',
            'parent' => $A,
            'public' => true,
            'workspace' => $workspace,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertSame('A', $data[0]['title']);
    }

    public function testSearchPublicCollectionsInPrivateWorkspaceAsAnonymousUser(): void
    {
        $this->createCollection([
            'title' => 'Foo',
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
            'title' => 'Foo',
            'public' => true,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertEquals($collection->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchNonPublicCollectionsAsAnonymousUser(): void
    {
        $this->createCollection([
            'title' => 'Foo',
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
        $asset = $this->createCollection([
            'title' => 'Foo',
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $this->defaultWorkspace->getId());

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $client = self::createClient();
        $response = $client->request('GET', '/collections', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchOwnedCollectionsAsOwnerButNotAllowedToWorkspace(): void
    {
        $asset = $this->createCollection([
            'title' => 'Foo',
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
            'title' => 'Foo',
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
            'title' => 'Foo',
            'ownerId' => 'another_owner',
        ]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $this->defaultWorkspace->getId());

        self::releaseIndex();

        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $collection,
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
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchCollectionsWithACEOnAllCollections(): void
    {
        $collection = $this->createCollection([
            'title' => 'Foo',
            'ownerId' => 'another_owner',
        ]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $this->defaultWorkspace->getId());
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
        $this->assertEquals('Foo', $data[0]['title']);
    }
}
