<?php

declare(strict_types=1);

namespace App\Tests\Search;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;

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
            'name' => 'Foo',
            'public' => true,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    public function testSearchNonPublicAssetsAsAnonymousUser(): void
    {
        $this->createAsset([
            'name' => 'Foo',
            'public' => false,
            'ownerId' => 'OWNER',
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets');
        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
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
            'name' => 'Foo',
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    public function testSearchNonOwnedAssetsAsOwner(): void
    {
        $this->createAsset([
            'name' => 'Bar',
            'ownerId' => 'another_owner',
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
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
            'name' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    public function testSearchAssetsFromNonOwnedCollectionAsOwner(): void
    {
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $this->createAsset([
            'name' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);

        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
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
            'name' => 'Foo',
            'collectionId' => $collection->getId(),
        ]);
        self::releaseIndex();

        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $asset,
            PermissionInterface::VIEW
        );
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
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
            'name' => 'Foo',
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

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    public function testSearchAssetsWithACEOnCollection(): void
    {
        $collection = $this->createCollection([
            'ownerId' => 'another_owner',
        ]);
        $asset = $this->createAsset([
            'name' => 'Foo',
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

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
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
            'name' => 'Foo',
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

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertEquals(1, is_countable($data) ? count($data) : 0);
        $this->assertEquals($asset->getId(), $data[0]['id']);
        $this->assertEquals('Foo', $data[0]['name']);
    }

    /**
     * @dataProvider getAssetTagsDataSet
     */
    public function testSearchAssetsWithAttributeFilterRuleOnTags(
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
                'name' => $assetName,
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

        $conditionParts = array_map(fn (string $id): string => sprintf('@tag = "%s"', $id), $include);
        if (!empty($exclude)) {
            $conditionParts[] = sprintf('@tag NOT IN (%s)', implode(', ', array_map(fn (string $id): string => sprintf('"%s"', $id), $exclude)));
        }
        $condition = implode(' AND ', $conditionParts);

        if ('' !== $condition) {
            self::getAttributeFilterManager()->saveRule(
                $workspace,
                [KeycloakClientTestMock::USER_UID],
                [],
                $condition
            );
        }
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];

        $this->assertSameSize($expectedResults, $data);
        $hasNamedAsset = function (string $name) use ($data): bool {
            foreach ($data as $asset) {
                if ($asset['name'] === $name) {
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

    /**
     * @dataProvider getAttributeConditionsDataSet
     */
    public function testSearchAssetsWithAttributeFilterRuleOnAttribute(
        string $condition,
        array $expectedResults,
    ): void {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
        ]);
        $definition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Category',
            'slug' => 'category',
        ]);

        foreach ([
            'Press' => 'press',
            'Internal' => 'internal',
            'None' => null,
        ] as $assetName => $category) {
            $this->createAsset([
                'workspace' => $workspace,
                'name' => $assetName,
                'public' => true,
                'collectionId' => $collection->getId(),
                'attributes' => null !== $category ? [[
                    'definition' => $definition,
                    'value' => $category,
                ]] : [],
            ]);
        }
        self::releaseIndex();

        self::getAttributeFilterManager()->saveRule(
            $workspace,
            [KeycloakClientTestMock::USER_UID],
            [],
            $condition
        );
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $names = array_map(fn (array $asset): string => $asset['name'], $data);
        sort($names);
        sort($expectedResults);

        $this->assertSame($expectedResults, $names);
    }

    public function getAttributeConditionsDataSet(): array
    {
        return [
            'equals' => ['category = "press"', ['Press']],
            'not equals' => ['NOT (category = "press")', ['Internal', 'None']],
            'in' => ['category IN ("press", "internal")', ['Internal', 'Press']],
            // Invalid condition (unknown field): fail-closed, the workspace is hidden
            'unknown field' => ['unknown_field = "press"', []],
        ];
    }

    /**
     * @dataProvider getTargetingDataSet
     */
    public function testAttributeFilterRuleTargeting(array $userIds, bool $applies): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
        ]);
        $definition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Category',
            'slug' => 'category',
        ]);

        foreach ([
            'Press' => 'press',
            'Internal' => 'internal',
        ] as $assetName => $category) {
            $this->createAsset([
                'workspace' => $workspace,
                'name' => $assetName,
                'public' => true,
                'collectionId' => $collection->getId(),
                'attributes' => [[
                    'definition' => $definition,
                    'value' => $category,
                ]],
            ]);
        }
        self::releaseIndex();

        self::getAttributeFilterManager()->saveRule(
            $workspace,
            $userIds,
            [],
            'category = "press"'
        );
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $names = array_map(fn (array $asset): string => $asset['name'], $data);
        sort($names);

        $this->assertSame($applies ? ['Press'] : ['Internal', 'Press'], $names);
    }

    public function getTargetingDataSet(): array
    {
        return [
            'no target applies to everyone' => [[], true],
            'targeted user' => [[KeycloakClientTestMock::USER_UID], true],
            'other user only' => [[KeycloakClientTestMock::OTHER_USER_UID], false],
            'multiple users including current' => [[KeycloakClientTestMock::OTHER_USER_UID, KeycloakClientTestMock::USER_UID], true],
        ];
    }
}
