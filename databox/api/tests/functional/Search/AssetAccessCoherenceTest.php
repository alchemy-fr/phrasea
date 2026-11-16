<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\DataboxExtraPermissionInterface;
use App\Tests\Functional\AbstractSearchTestCase;
use Elastica\Index;
use Elastica\Query;
use Elastica\Result;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The asset search answers totals, facets and aggregations straight from Elasticsearch: only
 * the page items go through the AssetVoter. Each asset Elasticsearch lets a user find must
 * therefore be one the AssetVoter lets them read, and the other way round.
 */
final class AssetAccessCoherenceTest extends AbstractSearchTestCase
{
    private const string WS_OWNER = 'ws_owner';
    private const string ASSET_OWNER = 'asset_owner';
    private const string COLLECTION_OWNER = 'coll_owner';
    private const string QUARANTINE_GROUP = 'quarantine_group';

    /**
     * @var array<string, string> asset IDs by fixture name
     */
    private array $assets = [];

    /**
     * Kept referenced: the voters cache their decisions by token object ID.
     *
     * @var TokenInterface[]
     */
    private array $tokens = [];

    public function testElasticsearchFindsWhatTheVoterReads(): void
    {
        $this->createFixtures();
        self::populateSearchIndices();
        self::getEntityManager()->clear();

        /** @var array<string, array{0: ?string, 1: string[]}> $users */
        $users = [
            'anonymous' => [null, []],
            'non member' => ['non_member', []],
            'member' => ['member', []],
            'workspace owner' => [self::WS_OWNER, []],
            'asset owner' => [self::ASSET_OWNER, []],
            'collection owner' => [self::COLLECTION_OWNER, []],
            'ACE VIEW on the private collection' => ['ace_view', []],
            'ACE CHILD_VIEW on the workspace' => ['child_view', []],
            'ACE EDIT on the collection' => ['editor', []],
            'ACE DELETE on the collection' => ['deleter', []],
            'ACE CHILD_DELETE on the workspace' => ['ws_deleter', []],
            'ACE VIEW|CREATE on the asset' => ['view_create', []],
            'group with the quarantine on the workspace' => ['quarantine_member', [self::QUARANTINE_GROUP]],
        ];

        $mismatches = [];
        $readable = [];
        foreach ($users as $label => [$userId, $groupIds]) {
            $this->authenticate($userId, $groupIds);
            $found = $this->findVisibleIds($userId, $groupIds, array_values($this->assets));

            foreach ($this->assets as $name => $id) {
                $asset = self::getEntityManager()->find(Asset::class, $id);
                $granted = self::getService(Security::class)->isGranted(AbstractVoter::READ, $asset);
                $isFound = in_array($id, $found, true);
                if ($granted) {
                    $readable[$label][] = $name;
                }
                if ($granted !== $isFound) {
                    $mismatches[] = sprintf('%s / %s: the voter %s, Elasticsearch %s', $label, $name, $granted ? 'grants' : 'denies', $isFound ? 'finds it' : 'does not');
                }
            }
        }

        $this->assertSame([], $mismatches);

        // The cases the alignment is about, so that the comparison does not pass on nothing
        $this->assertEqualsCanonicalizing(['public_ws_p2', 'public_ws_p3', 'public_ws_p4', 'public_ws_p5'], $readable['non member']);
        $this->assertSame(['public_ws_p5'], $readable['anonymous']);
        $this->assertEqualsCanonicalizing([
            'p2', 'p3', 'p4', 'p5',
            'in_collection_p2', 'in_collection_p4', 'in_collection_p5',
            'under_public_parent_in_private', 'public_under_private_parent',
            'private_story', 'in_private_story', 'all_users_ace',
            'public_ws_p2', 'public_ws_p3', 'public_ws_p4', 'public_ws_p5',
        ], $readable['member']);
        $this->assertContains('in_collection_p3', $readable['ACE VIEW on the private collection']);
        $this->assertNotContains('deleted', $readable['ACE EDIT on the collection']);
        $this->assertContains('deleted', $readable['ACE DELETE on the collection']);
        $this->assertContains('deleted', $readable['ACE CHILD_DELETE on the workspace']);
        $this->assertContains('deleted', $readable['collection owner']);
        $this->assertNotContains('quarantined_with_ace', $readable['ACE VIEW|CREATE on the asset']);
        $this->assertContains('quarantined', $readable['group with the quarantine on the workspace']);
        $this->assertContains('quarantined', $readable['workspace owner']);
        $this->assertContains('deleted', $readable['workspace owner']);
    }

    private function createFixtures(): void
    {
        $em = self::getEntityManager();

        $workspace = $this->createWorkspace([
            'name' => 'Private',
            'slug' => 'coherence-private',
            'ownerId' => self::WS_OWNER,
        ]);
        $publicWorkspace = $this->createWorkspace([
            'name' => 'Public',
            'slug' => 'coherence-public',
            'ownerId' => 'public_ws_owner',
            'public' => true,
        ]);

        foreach (['member', self::ASSET_OWNER, self::COLLECTION_OWNER, 'ace_view', 'editor', 'deleter', 'view_create'] as $userId) {
            $this->grantUserOnObject($userId, $workspace, PermissionInterface::VIEW);
        }
        $this->grantUserOnObject('child_view', $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_VIEW);
        $this->grantUserOnObject('ws_deleter', $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_DELETE);
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_GROUP_VALUE,
            self::QUARANTINE_GROUP,
            Workspace::OBJECT_TYPE,
            $workspace->getId(),
            PermissionInterface::VIEW,
            [DataboxExtraPermissionInterface::PERM_QUARANTINE],
        );

        foreach (array_keys(WorkspaceItemPrivacyInterface::KEYS) as $privacy) {
            $this->addAsset('p'.$privacy, $workspace, $privacy);
            $this->addAsset('public_ws_p'.$privacy, $publicWorkspace, $privacy);

            $collection = $this->addCollection($workspace, $privacy);
            $this->addAsset('in_collection_p'.$privacy, $workspace, WorkspaceItemPrivacyInterface::SECRET, $collection);
            if (WorkspaceItemPrivacyInterface::PRIVATE === $privacy) {
                $this->grantUserOnObject('ace_view', $collection, PermissionInterface::VIEW);
            }
        }

        $publicParent = $this->addCollection($workspace, WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $privateChild = $this->addCollection($workspace, WorkspaceItemPrivacyInterface::PRIVATE, $publicParent);
        $this->addAsset('under_public_parent_in_private', $workspace, WorkspaceItemPrivacyInterface::SECRET, $privateChild);

        $privateParent = $this->addCollection($workspace, WorkspaceItemPrivacyInterface::PRIVATE);
        $publicChild = $this->addCollection($workspace, WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, $privateParent);
        $this->addAsset('public_under_private_parent', $workspace, WorkspaceItemPrivacyInterface::SECRET, $publicChild);

        // A private story still opens its assets to the members: the AssetVoter reads them through the story
        $story = $this->addAsset('private_story', $workspace, WorkspaceItemPrivacyInterface::PRIVATE);
        $storyCollection = new Collection();
        $storyCollection->setWorkspace($workspace);
        $storyCollection->setOwnerId(self::ASSET_OWNER);
        $em->persist($storyCollection);
        $story->setStoryCollection($storyCollection);
        $em->flush();
        $this->addAsset('in_private_story', $workspace, WorkspaceItemPrivacyInterface::SECRET, $storyCollection);

        $trashCollection = $this->addCollection($workspace, WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $deleted = $this->addAsset('deleted', $workspace, WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, $trashCollection);
        $deleted->setDeletedAt(new \DateTimeImmutable());
        $this->grantUserOnObject('editor', $trashCollection, PermissionInterface::EDIT);
        $this->grantUserOnObject('deleter', $trashCollection, PermissionInterface::DELETE);

        $quarantined = $this->addAsset('quarantined', $workspace, WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $quarantined->setStatus(AssetStatusEnum::Quarantined);
        // PERM_QUARANTINE is a metadata: VIEW|CREATE (the same integer as a mask) does not grant it
        $quarantinedWithAce = $this->addAsset('quarantined_with_ace', $workspace, WorkspaceItemPrivacyInterface::SECRET);
        $quarantinedWithAce->setStatus(AssetStatusEnum::Quarantined);
        $em->flush();
        $this->grantUserOnObject('view_create', $quarantinedWithAce, PermissionInterface::VIEW | PermissionInterface::CREATE);

        $allUsersAsset = $this->addAsset('all_users_ace', $workspace, WorkspaceItemPrivacyInterface::SECRET);
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            AccessControlEntryInterface::USER_WILDCARD,
            Asset::OBJECT_TYPE,
            $allUsersAsset->getId(),
            PermissionInterface::VIEW,
        );

        $em->flush();
    }

    private function addCollection(Workspace $workspace, int $privacy, ?Collection $parent = null): Collection
    {
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'ownerId' => self::COLLECTION_OWNER,
            'parent' => $parent,
            'no_flush' => true,
        ]);
        $collection->setPrivacy($privacy);
        self::getEntityManager()->flush();

        return $collection;
    }

    private function addAsset(string $name, Workspace $workspace, int $privacy, ?Collection $collection = null): Asset
    {
        $asset = $this->createAsset([
            'name' => $name,
            'workspace' => $workspace,
            'ownerId' => self::ASSET_OWNER,
            'collectionId' => $collection?->getId(),
            'no_flush' => true,
        ]);
        $asset->setPrivacy($privacy);
        self::getEntityManager()->flush();
        $this->assets[$name] = $asset->getId();

        return $asset;
    }

    /**
     * Among the given assets, the IDs of those the base filters of the search let the user find.
     * Deleted and quarantined assets keep the scope of the AssetVoter.
     *
     * @param string[] $groupIds
     * @param string[] $assetIds
     *
     * @return string[]
     */
    private function findVisibleIds(?string $userId, array $groupIds, array $assetIds): array
    {
        try {
            $filterQueries = self::getService(AssetSearch::class)->createBaseFilterQueries($userId, $groupIds, filterDeleted: false, filterStatus: false);
        } catch (NoWorkspaceAllowedException) {
            return [];
        }

        $boolQuery = new Query\BoolQuery();
        foreach ($filterQueries as $filterQuery) {
            $boolQuery->addFilter($filterQuery);
        }
        $boolQuery->addFilter(new Query\Terms('_id', $assetIds));

        $query = new Query($boolQuery);
        $query->setSource(false);
        $query->setSize(count($assetIds));

        /** @var Index $index */
        $index = self::getContainer()->get('fos_elastica.index.asset');

        return array_map(
            static fn (Result $result): string => (string) $result->getId(),
            $index->search($query)->getResults(),
        );
    }

    /**
     * @param string[] $groupIds
     */
    private function authenticate(?string $userId, array $groupIds): void
    {
        self::getService(TemporaryCacheFactory::class)->reset();
        self::getPermissionManager()->resetCache();

        $token = null;
        if (null !== $userId) {
            $user = new JwtUser('JWT', $userId, $userId, ['ROLE_USER'], $groupIds);
            $token = $this->tokens[] = new UsernamePasswordToken($user, 'main', $user->getRoles());
        }
        self::getService(TokenStorageInterface::class)->setToken($token);
    }
}
