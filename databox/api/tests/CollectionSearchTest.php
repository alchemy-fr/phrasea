<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use App\Tests\Search\AbstractSearchTest;

class CollectionSearchTest extends AbstractSearchTest
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('collection');
    }

    public function testSearchPublicCollectionsAsAnonymousUser(): void
    {
        $collection = $this->createCollection([
            'title' => 'Foo',
            'public' => true,
        ]);

        self::releaseIndex();

        $response = $this->request(
            null,
            'GET',
            '/collections'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
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

        $response = $this->request(
            null,
            'GET',
            '/collections'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchOwnedCollectionsAsOwner(): void
    {
        $asset = $this->createCollection([
            'title' => 'Foo',
            'ownerId' => AuthServiceClientTestMock::USER_UID,
        ]);

        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/collections'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchNonOwnedCollectionsAsOwner(): void
    {
        $this->createAsset([
            'title' => 'Foo',
            'ownerId' => 'another_owner',
        ]);

        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/collections'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchCollectionsWithACEOnCollection(): void
    {
        $collection = $this->createCollection([
            'title' => 'Foo',
            'ownerId' => 'another_owner',
        ]);
        self::releaseIndex();

        $this->grantUserOnObject(
            AuthServiceClientTestMock::USER_UID,
            $collection,
            PermissionInterface::VIEW
        );

        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/collections'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($collection->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchCollectionsWithACEOnAllCollections(): void
    {
        $collection = $this->createCollection([
            'title' => 'Foo',
            'ownerId' => 'another_owner',
        ]);
        self::releaseIndex();

        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER,
            AuthServiceClientTestMock::USER_UID,
            'collection',
            null,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/collections'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($collection->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }
}
