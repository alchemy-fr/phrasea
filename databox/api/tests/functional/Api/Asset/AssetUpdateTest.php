<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * PUT/PATCH /assets/{id}.
 */
final class AssetUpdateTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testOwnerUpdatesHisAsset(): void
    {
        $workspace = $this->createOwnedWorkspace('custom_owner');
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'tags' => ['old']]);
        $tag = $this->findOrCreateTagByName('new', $workspace);

        $data = $this->request('PUT', '/assets/'.$asset->getId(), self::OWNER, [
            'extraMetadata' => ['foo' => 'bar'],
            'externalId' => 'ext-2',
            'tags' => ['/tags/'.$tag->getId()],
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame($asset->getId(), $data['id']);
        $this->assertSame(['foo' => 'bar'], $data['extraMetadata']);
        $this->assertSame('ext-2', $data['externalId']);
        // Tags are replaced
        $this->assertSame(['new'], array_column($data['tags'], 'name'));
    }

    public function testPatchUsesMergePatch(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setExternalId('kept');
        self::getEntityManager()->flush();

        $data = $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, null, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode(['trackingId' => 'patched']),
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame('patched', $data['trackingId']);
        $this->assertSame('kept', $data['externalId']);

        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['trackingId' => 'plain json']);
        $this->assertResponseStatusCodeSame(415);
    }

    public function testEmptyTagListClearsTheTags(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'tags' => ['foo', 'bar']]);
        $other = $this->createAsset(['ownerId' => self::OWNER, 'tags' => ['foo']]);

        $data = $this->request('PUT', '/assets/'.$asset->getId(), self::OWNER, ['tags' => []])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $data['tags']);

        // Omitting them keeps them
        $data = $this->request('PUT', '/assets/'.$other->getId(), self::OWNER, ['extraMetadata' => ['a' => 1]])->toArray();
        $this->assertSame(['foo'], array_column($data['tags'], 'name'));
    }

    /**
     * Workspace, reference collection and key are only taken into account at
     * creation.
     */
    public function testCreationOnlyFieldsAreIgnored(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('other-ws');
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER]);

        $this->request('PUT', '/assets/'.$asset->getId(), self::OWNER, [
            'workspace' => '/workspaces/'.$otherWorkspace->getId(),
            'collection' => '/collections/'.$collection->getId(),
            'key' => 'late-key',
            'ownerId' => self::OTHER,
        ]);
        $this->assertResponseIsSuccessful();

        $asset = $this->reloadAsset($asset->getId());
        $this->assertSame($workspace->getId(), $asset->getWorkspaceId());
        $this->assertNull($asset->getReferenceCollection());
        $this->assertCount(0, $asset->getCollections());
        $this->assertNull($asset->getKey());
        $this->assertSame(self::OWNER, $asset->getOwnerId());
    }

    /**
     * "name" only fills the name attribute when it is empty.
     */
    public function testNameDoesNotOverwriteAnExistingTitle(): void
    {
        $this->createOwnedWorkspace();
        $titled = $this->createAsset(['ownerId' => self::OWNER, 'name' => 'Original']);
        $untitled = $this->createAsset(['ownerId' => self::OWNER]);

        $data = $this->request('PUT', '/assets/'.$titled->getId(), self::OWNER, ['name' => 'Renamed'])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame('Original', $data['name']);

        $data = $this->request('PUT', '/assets/'.$untitled->getId(), self::OWNER, ['name' => 'Named'])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame('Named', $data['name']);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function assetAclProvider(): iterable
    {
        yield 'VIEW' => [PermissionInterface::VIEW, 403];
        yield 'EDIT (attributes only)' => [PermissionInterface::VIEW | PermissionInterface::EDIT, 403];
        yield 'DELETE' => [PermissionInterface::VIEW | PermissionInterface::DELETE, 403];
        yield 'OPERATOR' => [PermissionInterface::VIEW | PermissionInterface::OPERATOR, 200];
        yield 'OWNER' => [PermissionInterface::OWNER, 200];
    }

    /**
     * @dataProvider assetAclProvider
     */
    public function testUpdateRequiresOperatorOnTheAsset(int $mask, int $expectedStatus): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $this->grantUserOnObject(self::OTHER, $asset, $mask);

        $this->request('PUT', '/assets/'.$asset->getId(), self::OTHER, ['extraMetadata' => ['by' => 'other']]);

        $this->assertResponseStatusCodeSame($expectedStatus);
        $this->assertSame(200 === $expectedStatus ? ['by' => 'other'] : [], $this->reloadAsset($asset->getId())->getExtraMetadata());
    }

    public function testChildOperatorOnTheWorkspaceAllowsUpdatingItsAssets(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW);

        $this->request('PUT', '/assets/'.$asset->getId(), self::OTHER, ['extraMetadata' => ['a' => 1]]);
        $this->assertResponseStatusCodeSame(403);

        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_OPERATOR);
        $this->request('PUT', '/assets/'.$asset->getId(), self::OTHER, ['extraMetadata' => ['a' => 1]]);
        $this->assertResponseIsSuccessful();
    }

    public function testChildOperatorOnTheReferenceCollectionAllowsUpdatingItsAssets(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $collection->getId()]);
        $this->grantUserOnObject(self::OTHER, $collection, PermissionInterface::CHILD_OPERATOR);

        $this->request('PUT', '/assets/'.$asset->getId(), self::OTHER, ['extraMetadata' => ['a' => 1]]);
        $this->assertResponseIsSuccessful();
    }

    public function testAnonymousAndUnknownAsset(): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'public' => true]);

        $this->request('PUT', '/assets/'.$asset->getId(), null, ['extraMetadata' => ['a' => 1]]);
        $this->assertResponseStatusCodeSame(401);

        $this->request('PUT', '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11', self::OWNER, ['extraMetadata' => ['a' => 1]]);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Changing the privacy needs EDIT_PERMISSIONS: editing the asset is not
     * enough, the change is then silently ignored.
     */
    public function testPrivacyChangeNeedsEditPermissions(): void
    {
        $workspace = $this->createOwnedWorkspace('custom_owner');
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER]);

        $data = $this->request('PUT', '/assets/'.$asset->getId(), self::OWNER, [
            'privacy' => WorkspaceItemPrivacyInterface::PUBLIC,
        ])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame(WorkspaceItemPrivacyInterface::SECRET, $data['privacy']);

        $data = $this->request('PUT', '/assets/'.$asset->getId(), self::ADMIN, [
            'privacyLabel' => 'public_in_workspace',
        ])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, $data['privacy']);
        $this->assertSame(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, $this->reloadAsset($asset->getId())->getPrivacy());
    }

    public function testUpdateTheSourceFileKeepsThePreviousOneAsAVersion(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $oldFile = $this->createFile($workspace);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setSource($oldFile);
        self::getEntityManager()->flush();

        $data = $this->request('PUT', '/assets/'.$asset->getId(), self::OWNER, [
            'sourceFile' => [
                'url' => 'https://example.com/new.png',
            ],
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame('image/png', $data['source']['type']);
        $this->assertNotSame($oldFile->getId(), $data['source']['id']);

        $asset = $this->reloadAsset($asset->getId());
        $versions = self::getEntityManager()->getRepository(AssetFileVersion::class)->findBy(['asset' => $asset->getId()]);
        $this->assertCount(1, $versions);
        $this->assertSame($oldFile->getId(), $versions[0]->getFile()->getId());
    }

    public function testStoryIsUpdatableLikeAnyAsset(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $story = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'isStory' => true,
            'name' => 'Story',
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);

        $data = $this->request('PUT', '/assets/'.$story['id'], self::OWNER, [
            'extraMetadata' => ['chapter' => 1],
            // Turning it into a story again (or not) at update is ignored
            'isStory' => false,
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame(['chapter' => 1], $data['extraMetadata']);
        $this->assertSame($story['storyCollection']['id'], $data['storyCollection']['id']);
        $this->assertTrue($this->reloadAsset($story['id'])->isStory());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function attributesAclProvider(): iterable
    {
        yield 'VIEW' => [PermissionInterface::VIEW, 403];
        yield 'OPERATOR (asset edition only)' => [PermissionInterface::VIEW | PermissionInterface::OPERATOR, 403];
        yield 'EDIT' => [PermissionInterface::VIEW | PermissionInterface::EDIT, 201];
    }

    /**
     * POST /assets/{id}/attributes needs EDIT_ATTRIBUTES (the EDIT bit), not
     * the OPERATOR one used by PUT.
     *
     * @dataProvider attributesAclProvider
     */
    public function testBatchAttributesRequireEditAttributes(int $mask, int $expectedStatus): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $this->createAttributeDefinition(['name' => 'Description', 'slug' => 'description']);
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $this->grantUserOnObject(self::OTHER, $asset, $mask);

        $response = $this->request('POST', '/assets/'.$asset->getId().'/attributes', self::OTHER, [
            'actions' => [['name' => 'description', 'value' => 'Edited']],
        ]);

        $this->assertResponseStatusCodeSame($expectedStatus);
        if (201 === $expectedStatus) {
            $this->assertSame(['Edited'], array_column($response->toArray()['attributes'], 'value'));
        }
    }

    public function testBatchAttributesOnAnUnknownAsset(): void
    {
        $this->markTestIncomplete('BUG: AssetAttributeBatchUpdateProcessor loads the asset with DoctrineUtil::findStrict() without $throw404: an unknown asset gives a 500 instead of a 404 (src/Api/Processor/AssetAttributeBatchUpdateProcessor.php:32).');

        $this->createOwnedWorkspace();

        $this->request('POST', '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11/attributes', self::OWNER, [
            'actions' => [['name' => 'description', 'value' => 'Edited']],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }
}
