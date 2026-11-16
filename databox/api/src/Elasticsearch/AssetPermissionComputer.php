<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Elasticsearch\Listener\Dto\AssetPermissionsDTO;
use App\Elasticsearch\Listener\Dto\CollectionPermissionsDTO;
use App\Elasticsearch\Listener\Dto\PermissionsDTO;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\DataboxExtraPermissionInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class AssetPermissionComputer
{
    private const string CACHE_KEY_PREFIX_ASSET = 'a.';
    private const string CACHE_KEY_PREFIX_COLLECTION = 'c.';
    private const string CACHE_KEY_PREFIX_WORKSPACE = 'w.';

    private ?CacheInterface $workspaceCache = null;
    private ?CacheInterface $collectionCache = null;
    private ?CacheInterface $assetCache = null;

    public function __construct(
        private readonly PermissionManager $permissionManager,
    ) {
    }

    public function setWorkspaceCache(CacheInterface $workspaceCache): void
    {
        $this->workspaceCache = $workspaceCache;
    }

    public function setCollectionCache(CacheInterface $collectionCache): void
    {
        $this->collectionCache = $collectionCache;
    }

    public function clearCollectionCache(): void
    {
        $this->collectionCache?->clear();
    }

    public function clearAssetCache(): void
    {
        $this->assetCache?->clear();
    }

    public function setAssetCache(CacheInterface $assetCache): void
    {
        $this->assetCache = $assetCache;
    }

    public function disableWorkspaceCache(): void
    {
        $this->workspaceCache = null;
    }

    public function disableCollectionCache(): void
    {
        $this->collectionCache = null;
    }

    public function disableAssetCache(): void
    {
        $this->assetCache = null;
    }

    public function getAssetPermissionFields(Asset $asset): AssetPermissionsDTO
    {
        if (null === $this->assetCache) {
            return $this->doGetAssetPermissionFields($asset);
        }

        return $this->assetCache->get(self::CACHE_KEY_PREFIX_ASSET.$asset->getId(), fn (): AssetPermissionsDTO => $this->doGetAssetPermissionFields($asset));
    }

    private function doGetAssetPermissionFields(Asset $asset): AssetPermissionsDTO
    {
        $bestPrivacy = $asset->getPrivacy();

        $permissions = new PermissionsDTO();

        // As AssetVoter grants READ, DELETE and QUARANTINE on the asset itself
        foreach ($this->permissionManager->getObjectAces($asset) as $access) {
            self::addAce(
                $permissions,
                $access,
                [PermissionInterface::VIEW, PermissionInterface::OWNER],
                [PermissionInterface::DELETE, PermissionInterface::OWNER],
                [PermissionInterface::OWNER],
            );
        }

        if (null !== $asset->getOwnerId()) {
            $permissions->users[] = $asset->getOwnerId();
        }

        $collectionsPaths = [];
        $stories = [];

        $workspaceInfo = $this->getWorkspaceHierarchyInfo($asset->getWorkspace());

        $permissions->mergeWith($workspaceInfo, false)->unique();

        foreach ($asset->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();
            $isReferenceCollection = $collection->getId() === $asset->getReferenceCollectionId();

            $collectionInfo = $this->getCollectionHierarchyInfo($collection);
            // A collection in the trash (or under a trashed parent) grants nothing on the assets it links,
            // as for CollectionVoter::ASSET_VIEW; the reference collection is covered by "collectionDeleted"
            $grantsRights = $isReferenceCollection || !$collectionInfo->deleted;
            if ($grantsRights) {
                $bestPrivacy = max($bestPrivacy, $collectionInfo->assetPrivacy);

                $permissions->mergeWith($collectionInfo->permissions, !$isReferenceCollection)->unique();
            }

            if (null !== $storyAsset = $collection->getStoryAsset()) {
                $stories[] = $storyAsset->getId();

                $storyPermissions = $this->getAssetPermissionFields($storyAsset);

                if ($grantsRights) {
                    // Whoever reads the story reads its assets (AssetVoter): the story privacy applies as is
                    $bestPrivacy = max($bestPrivacy, $storyPermissions->privacy);

                    $permissions->mergeWith($storyPermissions->permissions, !$isReferenceCollection)->unique();
                }

                $collectionsPaths = array_merge($collectionsPaths, $storyPermissions->collectionPaths);
            } else {
                $collectionsPaths[] = $collectionInfo->absolutePath;
            }
        }

        return new AssetPermissionsDTO(
            $bestPrivacy,
            $permissions->unique(),
            array_values(array_unique($collectionsPaths)),
            array_values(array_unique($stories)),
        );
    }

    public function getWorkspaceHierarchyInfo(Workspace $workspace): PermissionsDTO
    {
        if (null === $this->workspaceCache) {
            return $this->doGetWorkspaceHierarchyInfo($workspace);
        }

        return $this->workspaceCache->get(self::CACHE_KEY_PREFIX_WORKSPACE.$workspace->getId(), fn (): PermissionsDTO => $this->doGetWorkspaceHierarchyInfo($workspace));
    }

    private function doGetWorkspaceHierarchyInfo(Workspace $workspace): PermissionsDTO
    {
        $permissions = new PermissionsDTO();

        if (null !== $ownerId = $workspace->getOwnerId()) {
            $permissions->users[] = $ownerId;
            $permissions->deleteUsers[] = $ownerId;
            $permissions->quarantineUsers[] = $ownerId;
        }

        // As WorkspaceVoter grants ASSET_VIEW, ASSET_DELETE and ASSET_QUARANTINE
        foreach ($this->permissionManager->getObjectAces($workspace) as $access) {
            self::addAce(
                $permissions,
                $access,
                [PermissionInterface::CHILD_VIEW, PermissionInterface::CHILD_OWNER, PermissionInterface::OWNER],
                [PermissionInterface::CHILD_DELETE, PermissionInterface::CHILD_OWNER, PermissionInterface::OWNER],
                [PermissionInterface::OWNER],
            );
        }

        return $permissions->unique();
    }

    private function getCollectionHierarchyInfo(Collection $collection): CollectionPermissionsDTO
    {
        if (null === $this->collectionCache) {
            return $this->doGetCollectionHierarchyInfo($collection);
        }

        return $this->collectionCache->get(self::CACHE_KEY_PREFIX_COLLECTION.$collection->getId(), fn (): CollectionPermissionsDTO => $this->doGetCollectionHierarchyInfo($collection));
    }

    private function doGetCollectionHierarchyInfo(Collection $collection): CollectionPermissionsDTO
    {
        $permissions = new PermissionsDTO();

        if (!$collection->isStory() && null !== $ownerId = $collection->getOwnerId()) {
            $permissions->users[] = $ownerId;
            $permissions->deleteUsers[] = $ownerId;
        }

        // As CollectionVoter grants ASSET_VIEW, ASSET_DELETE and ASSET_QUARANTINE
        foreach ($this->permissionManager->getObjectAces($collection) as $access) {
            self::addAce(
                $permissions,
                $access,
                [PermissionInterface::VIEW, PermissionInterface::CHILD_VIEW, PermissionInterface::CHILD_OWNER, PermissionInterface::OWNER],
                [PermissionInterface::DELETE, PermissionInterface::CHILD_DELETE, PermissionInterface::CHILD_OWNER, PermissionInterface::OWNER],
                [],
            );
        }

        $assetPrivacy = self::getAssetPrivacyOpenedByCollection($collection->getPrivacy());
        if (null !== $parent = $collection->getParent()) {
            $parentInfo = $this->getCollectionHierarchyInfo($parent);

            $permissions->mergeWith($parentInfo->permissions, false)->unique();
            $assetPrivacy = max($assetPrivacy, $parentInfo->assetPrivacy);
        }

        return new CollectionPermissionsDTO(
            $assetPrivacy,
            $collection->getAbsolutePath(),
            $permissions->unique(),
            $collection->isDeleted(),
        );
    }

    /**
     * The privacy a collection gives to its assets: a private (or private in workspace) collection
     * lists its name but keeps its assets secret (CollectionVoter::ASSET_VIEW).
     */
    private static function getAssetPrivacyOpenedByCollection(int $collectionPrivacy): int
    {
        return match ($collectionPrivacy) {
            WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
            WorkspaceItemPrivacyInterface::PRIVATE => WorkspaceItemPrivacyInterface::SECRET,
            default => $collectionPrivacy,
        };
    }

    /**
     * Indexes the user or group of the ACE among those allowed to read, delete and quarantine the
     * assets, when the ACE grants one of the given permissions. The quarantine is also granted by
     * the PERM_QUARANTINE metadata (not a permission bit).
     *
     * @param int[] $viewPermissions
     * @param int[] $deletePermissions
     * @param int[] $quarantinePermissions
     */
    private static function addAce(
        PermissionsDTO $permissions,
        AccessControlEntryInterface $access,
        array $viewPermissions,
        array $deletePermissions,
        array $quarantinePermissions,
    ): void {
        $userId = $access->getUserId() ?? AccessControlEntryInterface::USER_WILDCARD;
        $isUser = AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType();
        $hasOne = static fn (array $perms): bool => array_any($perms, static fn (int $perm): bool => $access->hasPermission($perm));

        if ($hasOne($viewPermissions)) {
            if ($isUser) {
                $permissions->users[] = $userId;
            } else {
                $permissions->groups[] = $userId;
            }
        }

        if ($hasOne($deletePermissions)) {
            if ($isUser) {
                $permissions->deleteUsers[] = $userId;
            } else {
                $permissions->deleteGroups[] = $userId;
            }
        }

        if ($hasOne($quarantinePermissions)
            || in_array(DataboxExtraPermissionInterface::PERM_QUARANTINE, $access->getMetadata(), true)) {
            if ($isUser) {
                $permissions->quarantineUsers[] = $userId;
            } else {
                $permissions->quarantineGroups[] = $userId;
            }
        }
    }
}
