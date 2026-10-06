<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * POST /assets and POST /assets/multiple.
 */
final class AssetCreateTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testCreateAssetWithoutFile(): void
    {
        $workspace = $this->createOwnedWorkspace();

        $response = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'No file',
            'externalId' => 'ext-1',
            'trackingId' => 'track-1',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertMatchesUuid($data['id']);
        $this->assertSame('No file', $data['name']);
        $this->assertSame(WorkspaceItemPrivacyInterface::SECRET, $data['privacy']);
        $this->assertSame(self::OWNER, $data['owner']['id']);
        $this->assertSame('ext-1', $data['externalId']);
        $this->assertSame('track-1', $data['resolvedTrackingId']);
        $this->assertArrayNotHasKey('source', $data);
        $this->assertArrayNotHasKey('referenceCollection', $data);
        $this->assertSame([], $data['collections']);
        $this->assertTrue($data['capabilities']['edit']);

        $asset = $this->reloadAsset($data['id']);
        $this->assertSame(self::OWNER, $asset->getOwnerId());
        $this->assertSame($workspace->getId(), $asset->getWorkspaceId());
        $this->assertNull($asset->getKey());
    }

    public function testCreateAssetFromASourceUrl(): void
    {
        $workspace = $this->createOwnedWorkspace();

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'sourceFile' => [
                'url' => 'https://example.com/files/photo.jpg',
                'originalName' => 'photo.jpg',
            ],
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        // The type is guessed from the extension
        $this->assertSame('image/jpeg', $data['source']['type']);
        $this->assertSame('https://example.com/files/photo.jpg', $data['source']['url']);

        $asset = $this->reloadAsset($data['id']);
        $this->assertSame('url', $asset->getSource()->getStorage());
        $this->assertSame($workspace->getId(), $asset->getSource()->getWorkspaceId());
    }

    public function testPrivateSourceUrlIsNotExposed(): void
    {
        $workspace = $this->createOwnedWorkspace();

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'sourceFile' => [
                'url' => 'https://example.com/private/doc.pdf',
                'isPrivate' => true,
            ],
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('application/pdf', $data['source']['type']);
        $this->assertArrayNotHasKey('url', $data['source']);
    }

    public function testCreateAssetFromAnExistingFileOfTheWorkspace(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $file = $this->createFile($workspace);

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'sourceFileId' => $file->getId(),
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($file->getId(), $data['source']['id']);
    }

    public function testCreateAssetFromAFileOfAnotherWorkspaceIsRejected(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('other-ws');
        $file = $this->createFile($otherWorkspace);

        $response = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'sourceFileId' => $file->getId(),
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('does not belong to workspace', $this->errorMessage($response));
    }

    public function testCreateAssetWithTags(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $foo = $this->findOrCreateTagByName('foo', $workspace);
        $bar = $this->findOrCreateTagByName('bar', $workspace);

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'tags' => ['/tags/'.$foo->getId(), '/tags/'.$bar->getId()],
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertEqualsCanonicalizing(['foo', 'bar'], array_column($data['tags'], 'name'));
    }

    public function testCreateAssetWithATagOfAnotherWorkspaceIsRejected(): void
    {
        $this->markTestIncomplete('BUG: a tag of another workspace makes Asset::addTag() throw a \\InvalidArgumentException, answered as a 500 instead of a 4xx (AssetInputTransformer.php:141 -> Asset.php:602).');

        $workspace = $this->createOwnedWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('other-ws');
        $tag = $this->findOrCreateTagByName('foreign', $otherWorkspace);

        $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'tags' => ['/tags/'.$tag->getId()],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateAssetInACollection(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $collection = $this->createCollection(['ownerId' => self::OWNER, 'name' => 'Destination']);

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'collection' => '/collections/'.$collection->getId(),
            'relationExtraMetadata' => ['foo' => 'bar'],
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('/collections/'.$collection->getId(), $data['referenceCollection']['@id']);
        $this->assertSame(['Destination'], array_column($data['collections'], 'name'));

        $asset = $this->reloadAsset($data['id']);
        $this->assertSame($collection->getId(), $asset->getReferenceCollectionId());
        $this->assertCount(1, $asset->getCollections());
    }

    public function testWorkspaceIsDeducedFromTheCollection(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $collection = $this->createCollection(['ownerId' => self::OWNER]);

        $data = $this->request('POST', '/assets', self::OWNER, [
            'collection' => '/collections/'.$collection->getId(),
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('/workspaces/'.$workspace->getId(), $data['workspace']['@id']);
        $this->assertSame($collection->getId(), $this->reloadAsset($data['id'])->getReferenceCollectionId());
    }

    public function testWorkspaceOrCollectionIsRequired(): void
    {
        $this->createOwnedWorkspace();

        // The access check (CREATE needs a workspace) runs before the validation...
        $this->request('POST', '/assets', self::OWNER, [
            'name' => 'Nowhere',
        ]);
        $this->assertResponseStatusCodeSame(403);

        // ...which an admin reaches
        $this->request('POST', '/assets', self::ADMIN, [
            'name' => 'Nowhere',
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, self::getEntityManager()->getRepository(Asset::class)->count([]));
    }

    public function testCreateAssetInACollectionOfAnotherWorkspaceIsRejected(): void
    {
        $this->markTestIncomplete('BUG: a collection of another workspace makes Asset::addToCollection() throw a \\InvalidArgumentException, answered as a 500 instead of a 400 (AssetInputTransformer.php:104 -> Asset.php:565).');

        $workspace = $this->createOwnedWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('other-ws');
        $collection = $this->createCollection(['ownerId' => self::OWNER, 'workspace' => $otherWorkspace]);

        $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'collection' => '/collections/'.$collection->getId(),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testNameIsLimitedTo255Characters(): void
    {
        $workspace = $this->createOwnedWorkspace();

        $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => str_repeat('a', 256),
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function privacyProvider(): iterable
    {
        yield 'privacy' => [['privacy' => WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE], WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE];
        yield 'privacyLabel' => [['privacyLabel' => 'public'], WorkspaceItemPrivacyInterface::PUBLIC];
        yield 'privacy wins over privacyLabel' => [['privacy' => WorkspaceItemPrivacyInterface::PRIVATE, 'privacyLabel' => 'public'], WorkspaceItemPrivacyInterface::PRIVATE];
        yield 'none' => [[], WorkspaceItemPrivacyInterface::SECRET];
    }

    /**
     * @dataProvider privacyProvider
     */
    public function testWorkspaceOwnerSetsThePrivacy(array $payload, int $expectedPrivacy): void
    {
        $workspace = $this->createOwnedWorkspace();

        $data = $this->request('POST', '/assets', self::OWNER, array_merge([
            'workspace' => '/workspaces/'.$workspace->getId(),
        ], $payload))->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($expectedPrivacy, $data['privacy']);
    }

    public function testInvalidPrivacyLabelIsRejected(): void
    {
        $workspace = $this->createOwnedWorkspace();

        $response = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'privacyLabel' => 'everybody',
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Invalid privacyLabel', $this->errorMessage($response));
    }

    public function testOutOfRangePrivacyIsRejected(): void
    {
        $this->markTestIncomplete('BUG: AssetInput::$privacy has no Assert\Choice: any integer is stored (e.g. 42, read as "more than public" by the voters) (src/Api/Model/Input/AssetInput.php:26, src/Api/InputTransformer/AbstractInputTransformer.php:33).');

        $workspace = $this->createOwnedWorkspace();

        $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'privacy' => 42,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Without EDIT_PERMISSIONS (workspace owner or explicit grant), the
     * requested privacy is silently ignored.
     */
    public function testContributorCannotChooseThePrivacy(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->grantContributor(self::OTHER, $workspace);

        $data = $this->request('POST', '/assets', self::OTHER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'privacy' => WorkspaceItemPrivacyInterface::PUBLIC,
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(WorkspaceItemPrivacyInterface::SECRET, $data['privacy']);
        $this->assertSame(self::OTHER, $data['owner']['id']);
        $this->assertTrue($data['capabilities']['edit']);
        $this->assertFalse($data['capabilities']['editPermissions']);
    }

    public function testCreationRequiresTheChildCreatePermission(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $payload = ['workspace' => '/workspaces/'.$workspace->getId()];

        // Not even member of the workspace
        $this->request('POST', '/assets', self::OTHER, $payload);
        $this->assertResponseStatusCodeSame(403);

        // Member (VIEW) only
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $this->request('POST', '/assets', self::OTHER, $payload);
        $this->assertResponseStatusCodeSame(403);

        $this->grantContributor(self::OTHER, $workspace);
        $this->request('POST', '/assets', self::OTHER, $payload);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testChildCreateOnACollectionAllowsCreatingInIt(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $this->grantUserOnObject(self::OTHER, $collection, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);

        $this->request('POST', '/assets', self::OTHER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/assets', self::OTHER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'collection' => '/collections/'.$collection->getId(),
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testAnonymousCannotCreate(): void
    {
        $workspace = $this->createOwnedWorkspace(self::OWNER, ['public' => true]);

        // The owner is resolved before the access check: without user, it must be given...
        $response = $this->request('POST', '/assets', null, [
            'workspace' => '/workspaces/'.$workspace->getId(),
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('ownerId', $this->errorMessage($response));

        // ...and even then, an anonymous user cannot create in a public workspace
        $this->request('POST', '/assets', null, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'ownerId' => self::OWNER,
        ]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(0, self::getEntityManager()->getRepository(Asset::class)->count([]));
    }

    public function testClientCredentialsMustProvideTheOwner(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $headers = ['Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('asset:create asset:read')];

        $response = $this->request('POST', '/assets', null, [
            'workspace' => '/workspaces/'.$workspace->getId(),
        ], ['headers' => $headers]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('ownerId', $this->errorMessage($response));

        $data = $this->request('POST', '/assets', null, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'ownerId' => self::OTHER,
        ], ['headers' => $headers])->toArray();
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(self::OTHER, $this->reloadAsset($data['id'])->getOwnerId());
    }

    /**
     * The key identifies an asset within its workspace: posting it again
     * updates the existing asset instead of creating a new one. The name only
     * fills an empty name attribute, so it is kept.
     */
    public function testKeyMakesCreationIdempotent(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $payload = [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'key' => 'my-key',
            'name' => 'First',
            'extraMetadata' => ['v' => 1],
        ];

        $first = $this->request('POST', '/assets', self::OWNER, $payload)->toArray();
        $this->assertResponseStatusCodeSame(201);

        $second = $this->request('POST', '/assets', self::OWNER, array_merge($payload, [
            'name' => 'Second',
            'extraMetadata' => ['v' => 2],
        ]))->toArray();
        $this->assertResponseStatusCodeSame(201);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('First', $second['name']);
        $this->assertSame(['v' => 2], $second['extraMetadata']);
        $this->assertSame(1, self::getEntityManager()->getRepository(Asset::class)->count(['key' => 'my-key']));
    }

    public function testSameKeyInAnotherWorkspaceCreatesAnotherAsset(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('other-ws');

        $first = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'key' => 'shared-key',
        ])->toArray();
        $second = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$otherWorkspace->getId(),
            'key' => 'shared-key',
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        $this->assertNotSame($first['id'], $second['id']);
    }

    /**
     * Reusing the key of an asset the user cannot edit must not let him
     * overwrite it.
     */
    public function testKeyOfAnAssetTheUserCannotEditIsNotOverwritable(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $existing = $this->createAsset(['ownerId' => self::OWNER, 'name' => 'Original', 'no_flush' => true]);
        $existing->setKey('secret-key');
        self::getEntityManager()->flush();
        $this->grantContributor(self::OTHER, $workspace);

        $this->request('POST', '/assets', self::OTHER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'key' => 'secret-key',
            'name' => 'Hijacked',
            'extraMetadata' => ['hijacked' => true],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $asset = $this->reloadAsset($existing->getId());
        $this->assertSame([], $asset->getExtraMetadata());
    }

    public function testCreateMultipleAssets(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $collection = $this->createCollection(['ownerId' => self::OWNER]);

        $response = $this->request('POST', '/assets/multiple', self::OWNER, [
            'assets' => [
                ['workspace' => '/workspaces/'.$workspace->getId(), 'name' => 'A', 'collection' => '/collections/'.$collection->getId()],
                ['workspace' => '/workspaces/'.$workspace->getId(), 'name' => 'B'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame(['A', 'B'], array_column($data['assets'], 'name'));
        foreach ($data['assets'] as $asset) {
            $this->assertMatchesUuid($asset['id']);
            $this->assertSame(self::OWNER, $this->reloadAsset($asset['id'])->getOwnerId());
        }
        $this->assertSame($collection->getId(), $this->reloadAsset($data['assets'][0]['id'])->getReferenceCollectionId());
    }

    public function testCreateMultipleAssetsAsAStory(): void
    {
        $workspace = $this->createOwnedWorkspace();

        $data = $this->request('POST', '/assets/multiple', self::OWNER, [
            'isStory' => true,
            'story' => ['name' => 'My story'],
            'assets' => [
                ['workspace' => '/workspaces/'.$workspace->getId(), 'name' => 'Page 1'],
                ['workspace' => '/workspaces/'.$workspace->getId(), 'name' => 'Page 2'],
            ],
        ])->toArray();

        $this->assertResponseStatusCodeSame(201);
        // The story asset comes first
        $this->assertSame(['My story', 'Page 1', 'Page 2'], array_column($data['assets'], 'name'));

        $story = $this->reloadAsset($data['assets'][0]['id']);
        $this->assertTrue($story->isStory());
        $storyCollectionId = $story->getStoryCollection()->getId();
        foreach ([1, 2] as $i) {
            $this->assertSame($storyCollectionId, $this->reloadAsset($data['assets'][$i]['id'])->getReferenceCollectionId());
        }
    }

    public function testCreateMultipleAssetsIsAllOrNothing(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $forbiddenWorkspace = $this->createAnotherWorkspace('forbidden-ws', self::ADMIN);

        $this->request('POST', '/assets/multiple', self::OWNER, [
            'assets' => [
                ['workspace' => '/workspaces/'.$workspace->getId(), 'name' => 'Allowed'],
                ['workspace' => '/workspaces/'.$forbiddenWorkspace->getId(), 'name' => 'Forbidden'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(0, self::getEntityManager()->getRepository(Asset::class)->count([]));
    }

    private function grantContributor(string $userId, Workspace $workspace): void
    {
        $this->grantUserOnObject($userId, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);
    }
}
