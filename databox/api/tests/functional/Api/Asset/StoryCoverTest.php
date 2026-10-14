<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * PUT /assets/{id}/story-cover: the source file of a story item is copied as
 * the story source.
 */
final class StoryCoverTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testItemSourceIsCopiedAsStorySource(): void
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

        $story = $this->reloadAsset($storyId);
        $storySource = $story->getSource();
        $this->assertNotNull($storySource);
        $this->assertNotSame($itemFile->getId(), $storySource->getId());
        $this->assertSame($itemFile->getPath(), $storySource->getPath());
        $this->assertSame($workspace->getId(), $storySource->getWorkspaceId());
        $this->assertSame($itemFile->getId(), $this->reloadAsset($item->getId())->getSource()->getId());
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
