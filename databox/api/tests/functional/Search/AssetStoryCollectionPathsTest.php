<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Listener\AclAssetIndexUpdateService;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * Members of a story only belong to the collections they are directly attached to,
 * not to the collections of the story asset. They still receive the ACL granted on
 * the collections of the story.
 */
class AssetStoryCollectionPathsTest extends AbstractSearchTestCase
{
    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    public function testStoryMembersAreNotInStoryCollections(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'C',
            'public' => true,
        ]);

        $story = $this->createStoryAsset($workspace, $collection, 'Story', true);
        $this->createStoryMember($workspace, $story, 'Member', true);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Direct',
            'public' => true,
            'collectionId' => $collection->getId(),
        ]);

        self::releaseIndex();

        $collectionId = $collection->getId();

        $this->assertSearch(['parents' => [$collectionId]], ['Direct', 'Story']);
        $this->assertSearch(['conditions' => [sprintf('@collection = "%s"', $collectionId)]], ['Direct', 'Story']);
        $this->assertSearch(['collection' => $collectionId], ['Direct', 'Story']);
        $this->assertSearch(['directCollections' => [$collectionId]], ['Direct', 'Story']);
        $this->assertSearch(['story' => $story->getId()], ['Member']);
    }

    public function testCollectionAclReachesStoryMembers(): void
    {
        $workspace = $this->createWorkspace([
            'no_flush' => true,
        ]);
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $workspace,
            PermissionInterface::VIEW
        );
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'C',
            'ownerId' => 'another_owner',
        ]);

        $story = $this->createStoryAsset($workspace, $collection, 'Story', false);
        $this->createStoryMember($workspace, $story, 'Member', false);

        self::releaseIndex();

        $this->assertSearch([], [], KeycloakClientTestMock::USER_UID);

        // The ACE is checked when serving the assets; its indexing (AclAddUserToCollectionAssets
        // through the bus) is replayed below, as test transports are not consumed.
        $this->grantUserOnObject(
            KeycloakClientTestMock::USER_UID,
            $collection,
            PermissionInterface::VIEW
        );

        /** @var AclAssetIndexUpdateService $aclAssetIndexUpdateService */
        $aclAssetIndexUpdateService = self::getContainer()->get(AclAssetIndexUpdateService::class);
        $aclAssetIndexUpdateService->addAllowedUserOrGroupToCollection(
            $collection->getId(),
            AccessControlEntryInterface::TYPE_USER_VALUE,
            KeycloakClientTestMock::USER_UID,
        );
        self::waitForESIndex('asset');

        $this->assertSearch([], ['Member', 'Story'], KeycloakClientTestMock::USER_UID);
    }

    private function createStoryAsset(Workspace $workspace, Collection $collection, string $name, bool $public): Asset
    {
        $story = $this->createAsset([
            'workspace' => $workspace,
            'name' => $name,
            'public' => $public,
            'collectionId' => $collection->getId(),
            'no_flush' => true,
        ]);

        $em = self::getEntityManager();
        $storyCollection = new Collection();
        $storyCollection->setWorkspace($workspace);
        $storyCollection->setOwnerId($story->getOwnerId());
        $em->persist($storyCollection);
        $story->setStoryCollection($storyCollection);
        $em->flush();

        return $story;
    }

    private function createStoryMember(Workspace $workspace, Asset $story, string $name, bool $public): Asset
    {
        $member = $this->createAsset([
            'workspace' => $workspace,
            'name' => $name,
            'public' => $public,
            'no_flush' => true,
        ]);

        $em = self::getEntityManager();
        $em->persist($member->addToCollection($story->getStoryCollection()));
        $em->flush();

        return $member;
    }

    /**
     * @param array<string, mixed> $query
     * @param string[]             $expectedNames
     */
    private function assertSearch(array $query, array $expectedNames, ?string $userId = null): void
    {
        $options = [
            'query' => $query,
        ];
        if (null !== $userId) {
            $options['headers'] = [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ];
        }

        $response = self::createClient()->request('GET', '/assets', $options);

        $data = $this->getDataFromResponse($response, 200)['member'];
        $names = array_map(fn (array $asset): ?string => $asset['name'] ?? null, $data);
        sort($names);

        self::assertSame($expectedNames, $names, sprintf('Unexpected results for %s', json_encode($query)));
    }
}
