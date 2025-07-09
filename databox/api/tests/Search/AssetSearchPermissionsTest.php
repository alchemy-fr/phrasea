<?php

declare(strict_types=1);

namespace App\Tests\Search;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\TagFilterRule;

class AssetSearchPermissionsTest extends AbstractSearchTest
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    public function testSearchPublicAssetsAsAnonymousUser(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $asset = $this->createAsset([
            'workspace' => $workspace,
            'title' => 'Foo',
            'public' => true,
        ]);
        self::releaseIndex();

        $response = $this->request(
            null,
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertCount(1, $data);
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
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchOwnedAssetsAsOwner(): void
    {
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $this->getOrCreateDefaultWorkspace(),
            PermissionInterface::VIEW
        );

        $asset = $this->createAsset([
            'title' => 'Foo',
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchNonOwnedAssetsAsOwner(): void
    {
        $this->createAsset([
            'title' => 'Bar',
            'ownerId' => 'another_owner',
        ]);

        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchAssetsFromOwnedCollectionAsOwner(): void
    {
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $this->getOrCreateDefaultWorkspace(),
            PermissionInterface::VIEW
        );
        $collection = $this->createCollection([
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);

        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
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
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEmpty($data);
    }

    public function testSearchAssetsWithACEOnAsset(): void
    {
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $this->getOrCreateDefaultWorkspace(),
            PermissionInterface::VIEW
        );
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);
        self::releaseIndex();

        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $asset,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchAssetsWithACEOnAllAssets(): void
    {
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $this->getOrCreateDefaultWorkspace(),
            PermissionInterface::VIEW
        );
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);
        self::releaseIndex();

        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            KeycloakClientTestMock::USER_UID,
            'asset',
            null,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
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
            KeycloakClientTestMock::USER_UID,
            $collection->getWorkspace(),
            PermissionInterface::VIEW
        );

        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $collection,
            PermissionInterface::VIEW
        );

        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    public function testSearchAssetsWithACEOnAllCollections(): void
    {
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $this->getOrCreateDefaultWorkspace(),
            PermissionInterface::VIEW
        );
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'title' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);
        self::releaseIndex();

        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            KeycloakClientTestMock::USER_UID,
            'collection',
            null,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['title']);
    }

    /**
     * @dataProvider getAssetTagsDataSet
     */
    public function testSearchAssetsWithTagFilterRuleOnCollection(
        array $assets,
        array $include,
        array $exclude,
        array $expectedResults,
    ): void {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
        ]);

        foreach ($assets as $assetName => $tags) {
            $this->createAsset([
                'workspace' => $workspace,
                'title' => $assetName,
                'public' => true,
                'collectionId' => $collection->getId(),
                'tags' => $tags,
            ]);
        }
        self::releaseIndex();

        $resolveTag = function (string $tagName) use ($workspace): string {
            $tag = $this->findOrCreateTagByName($tagName, $workspace);

            return $tag->getId();
        };
        $include = array_map($resolveTag, $include);
        $exclude = array_map($resolveTag, $exclude);

        self::getTagFilterManager()->updateRule(
            TagFilterRule::TYPE_USER,
            KeycloakClientTestMock::USER_UID,
            TagFilterRule::TYPE_COLLECTION,
            $collection->getId(),
            $include,
            $exclude
        );
        self::releaseIndex();

        $response = $this->request(
            KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            'GET',
            '/assets'
        );

        $data = $this->getDataFromResponse($response, 200);
        $this->assertSameSize($expectedResults, $data);
        $hasNamedAsset = function (string $name) use ($data): bool {
            foreach ($data as $asset) {
                if ($asset['title'] === $name) {
                    return true;
                }
            }

            return false;
        };
        foreach ($expectedResults as $expectedName) {
            $this->assertTrue($hasNamedAsset($expectedName), sprintf('Asset named "%s" was not found in results', $expectedName));
        }
    }

    public function getAssetTagsDataSet(): array
    {
        return [
            [['Foo' => ['tag1'], 'Bar' => []], [], [], ['Foo', 'Bar']],
            [['Foo' => ['tag1'], 'Bar' => []], ['tag1'], [], ['Foo']],
            [['Foo' => ['tag1'], 'Bar' => ['tag2']], ['tag1'], [], ['Foo']],
            [['Foo' => ['tag1'], 'Bar' => []], ['tag2'], [], []],
            [['Foo' => ['tag1'], 'Bar' => ['tag2']], ['tag2'], [], ['Bar']],
            [['Foo' => ['tag1'], 'Bar' => ['tag2', 'tag1']], ['tag2'], [], ['Bar']],
            [['Foo' => ['tag1'], 'Bar' => ['tag2', 'tag1']], [], ['tag1'], []],

            // Strange cases
            [['Foo' => ['tag1'], 'Bar' => ['tag2', 'tag1']], ['tag1'], ['tag1'], []],
            [['Foo' => [], 'Bar' => []], ['tag1'], ['tag1'], []],
        ];
    }
}
