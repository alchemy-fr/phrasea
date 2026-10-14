<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Story cover: the source file of a story item is shared as the story source,
 * set by PUT /assets/{id}/story-cover or at the story creation (POST /assets
 * with storyAssets and storyCover).
 */
final class StoryCoverTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testItemSourceIsSharedAsStorySource(): void
    {
        $workspace = $this->createOwnedWorkspace();
        [$storyId, $storyCollectionId] = $this->createStory($workspace);
        $item = $this->createItem($workspace, $storyCollectionId);
        $itemFile = $item->getSource();

        $data = $this->request('PUT', '/assets/'.$storyId.'/story-cover', self::OWNER, [
            'assetId' => $item->getId(),
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame($storyId, $data['id']);

        $this->assertSame($itemFile->getId(), $data['source']['id']);
        $this->assertSame($itemFile->getId(), $this->reloadAsset($storyId)->getSource()->getId());
        $this->assertSame($itemFile->getId(), $this->reloadAsset($item->getId())->getSource()->getId());

        // Marking the current cover again changes nothing
        $this->request('PUT', '/assets/'.$storyId.'/story-cover', self::OWNER, [
            'assetId' => $item->getId(),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([], self::getEntityManager()->getRepository(AssetFileVersion::class)->findBy(['asset' => $storyId]));
    }

    public function testCoverMustBeAnItemOfTheStory(): void
    {
        $workspace = $this->createOwnedWorkspace();
        [$storyId] = $this->createStory($workspace);
        $outsider = $this->createItem($workspace, null);

        $this->request('PUT', '/assets/'.$storyId.'/story-cover', self::OWNER, [
            'assetId' => $outsider->getId(),
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->reloadAsset($storyId)->getSource());
    }

    public function testTargetMustBeAStory(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $asset = $this->createItem($workspace, null);
        $other = $this->createItem($workspace, null);

        $this->request('PUT', '/assets/'.$asset->getId().'/story-cover', self::OWNER, [
            'assetId' => $other->getId(),
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testCoverWithoutSourceIsRejected(): void
    {
        $workspace = $this->createOwnedWorkspace();
        [$storyId, $storyCollectionId] = $this->createStory($workspace);
        $item = $this->createAsset([
            'workspace' => $this->reloadWorkspace($workspace),
            'ownerId' => self::OWNER,
            'collectionId' => $storyCollectionId,
        ]);

        $this->request('PUT', '/assets/'.$storyId.'/story-cover', self::OWNER, [
            'assetId' => $item->getId(),
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testStoryMustBeEditable(): void
    {
        $workspace = $this->createOwnedWorkspace();
        [$storyId, $storyCollectionId] = $this->createStory($workspace);
        $item = $this->createItem($workspace, $storyCollectionId);

        $this->request('PUT', '/assets/'.$storyId.'/story-cover', self::OTHER, [
            'assetId' => $item->getId(),
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->request('PUT', '/assets/'.$storyId.'/story-cover', null, [
            'assetId' => $item->getId(),
        ]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertNull($this->reloadAsset($storyId)->getSource());
    }

    public function testStoryIsCreatedWithItsItemsAndCover(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $first = $this->createItem($workspace, null);
        $cover = $this->createItem($workspace, null);

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'isStory' => true,
            'name' => 'Story',
            'storyAssets' => [$first->getId(), $cover->getId()],
            'storyCover' => $cover->getId(),
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);

        $story = $this->reloadAsset($data['id']);
        $storyCollectionId = $story->getStoryCollection()->getId();
        foreach ([$first, $cover] as $item) {
            $collectionIds = $this->reloadAsset($item->getId())->getCollections()
                ->map(fn ($ca) => $ca->getCollection()->getId())->toArray();
            $this->assertContains($storyCollectionId, $collectionIds);
        }
        $this->assertSame($cover->getSource()->getId(), $story->getSource()->getId());
    }

    public function testStoryIsCreatedWithItemsWithoutCover(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $item = $this->createItem($workspace, null);

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'isStory' => true,
            'storyAssets' => [$item->getId()],
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);
        $this->assertNull($this->reloadAsset($data['id'])->getSource());
    }

    public function testStoryCreationValidation(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $item = $this->createItem($workspace, null);
        $other = $this->createItem($workspace, null);
        $otherWorkspace = $this->createAnotherWorkspace('second-ws');
        $foreign = $this->createItem($otherWorkspace, null);
        $base = [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'isStory' => true,
        ];

        // The cover must be one of the items
        $this->request('POST', '/assets', self::OWNER, $base + [
            'storyAssets' => [$item->getId()],
            'storyCover' => $other->getId(),
        ]);
        $this->assertResponseStatusCodeSame(400);

        // The items must belong to the story workspace
        $this->request('POST', '/assets', self::OWNER, $base + [
            'storyAssets' => [$foreign->getId()],
        ]);
        $this->assertResponseStatusCodeSame(400);

        // Only at the creation of a story
        $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'storyAssets' => [$item->getId()],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testStoryItemsMustBeReadable(): void
    {
        $workspace = $this->createOwnedWorkspace();
        // A contributor may create assets, but not read the others' secret ones
        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);
        $private = $this->createItem($workspace, null);
        $story = [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'isStory' => true,
        ];

        $this->request('GET', '/assets/'.$private->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/assets', self::OTHER, $story);
        $this->assertResponseStatusCodeSame(201);

        $this->request('POST', '/assets', self::OTHER, $story + ['storyAssets' => [$private->getId()]]);
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{string, string} the story ID and its collection ID
     */
    private function createStory(Workspace $workspace): array
    {
        $story = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'isStory' => true,
            'name' => 'Story',
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);

        return [$story['id'], $story['storyCollection']['id']];
    }

    private function createItem(Workspace $workspace, ?string $collectionId): Asset
    {
        $workspace = $this->reloadWorkspace($workspace);
        $file = $this->createFile($workspace);
        $asset = $this->createAsset(array_filter([
            'workspace' => $workspace,
            'ownerId' => self::OWNER,
            'collectionId' => $collectionId,
            'no_flush' => true,
        ], static fn ($v) => null !== $v));
        $asset->setSource($file);
        self::getEntityManager()->flush();

        return $asset;
    }

    /**
     * Requests clear the entity manager: the workspace must be attached again.
     */
    private function reloadWorkspace(Workspace $workspace): Workspace
    {
        return self::getEntityManager()->find(Workspace::class, $workspace->getId());
    }
}
