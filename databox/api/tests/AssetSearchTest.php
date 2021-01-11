<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class AssetSearchTest extends AbstractSearchTest
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    public function testSearchPublicAssetsAsAnonymousUser(): void
    {
        $asset = $this->createAsset([
            'title' => 'Foo',
            'public' => true,
        ]);

        self::releaseIndex();

        $response = $this->request(
            null,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchNonPublicAssetsAsAnonymousUser(): void
    {
        $this->createAsset([
            'title' => 'Foo',
            'public' => false,
            'ownerId' => 'OWNER',
        ]);

        self::releaseIndex();

        $response = $this->request(
            null,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchOwnedAssetsAsOwner(): void
    {
        $asset = $this->createAsset([
            'title' => 'Foo',
            'ownerId' => AuthServiceClientTestMock::USER_UID,
        ]);

        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchNonOwnedAssetsAsOwner(): void
    {
        $this->createAsset([
            'title' => 'Foo',
            'ownerId' => 'another_owner',
        ]);

        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchAssetsFromOwnedCollectionAsOwner(): void
    {
        $collection = $this->createCollection([
            'ownerId' => AuthServiceClientTestMock::USER_UID,
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);

        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchAssetsFromNonOwnedCollectionAsOwner(): void
    {
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);

        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchAssetsWithACEOnAsset(): void
    {
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);
        self::releaseIndex();

        $this->grantUserOnObject(
            AuthServiceClientTestMock::USER_UID,
            $asset,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchAssetsWithACEOnAllAssets(): void
    {
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);
        self::releaseIndex();

        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER,
            AuthServiceClientTestMock::USER_UID,
            'asset',
            null,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchAssetsWithACEOnCollection(): void
    {
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
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
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchAssetsWithACEOnAllCollections(): void
    {
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
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
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }
}
