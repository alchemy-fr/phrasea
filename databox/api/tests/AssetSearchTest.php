<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use App\Tests\Search\AbstractSearchTest;

class AssetSearchTest extends AbstractSearchTest
{
    public function testSearchPublicAssetsAsAnonymousUser(): void
    {
        $assetId = $this->createAsset([
            'title' => 'Foo',
            'public' => true,
            'ownerId' => 'OWNER',
        ]);

        self::waitForESIndex('asset');

        $response = $this->request(
            null,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($assetId, $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchNonPublicAssetsAsAnonymousUser(): void
    {
        $this->createAsset([
            'title' => 'Foo',
            'public' => false,
            'ownerId' => 'OWNER',
        ]);

        self::waitForESIndex('asset');

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
        $assetId = $this->createAsset([
            'title' => 'Foo',
            'ownerId' => AuthServiceClientTestMock::USER_UID,
        ]);

        self::waitForESIndex('asset');

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($assetId, $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchNonOwnedAssetsAsOwner(): void
    {
        $this->createAsset([
            'title' => 'Foo',
            'ownerId' => 'another_owner',
        ]);

        self::waitForESIndex('asset');

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
        $collectionId = $this->createCollection([
            'ownerId' => AuthServiceClientTestMock::USER_UID,
        ]);
        $assetId = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collectionId,
        ]);

        self::waitForESIndex('asset');

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, count($data));
        $this->assertEquals($assetId, $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchAssetsFromNonOwnedCollectionAsOwner(): void
    {
        $collectionId = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collectionId,
        ]);

        self::waitForESIndex('asset');

        $response = $this->request(
            AuthServiceClientTestMock::USER_TOKEN,
            'GET',
            '/api/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }
}
