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

        return $this->assetCache->get($asset->getId(), fn (): AssetPermissionsDTO => $this->doGetAssetPermissionFields($asset));
    }

    private function doGetAssetPermissionFields(Asset $asset): AssetPermissionsDTO
    {
        $bestPrivacy = $asset->getPrivacy();

        $permissions = new PermissionsDTO();

        $aces = $this->permissionManager->getObjectAces($asset);
        foreach ($aces as $access) {
            $userId = $access->getUserId() ?? AccessControlEntryInterface::USER_WILDCARD;
            $isUser = AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType();
            if ($access->hasPermission(PermissionInterface::VIEW)) {
                if ($isUser) {
                    $permissions->users[] = $userId;
                } else {
                    $permissions->groups[] = $userId;
                }
            }

            if ($access->hasPermission(PermissionInterface::DELETE)) {
                if ($isUser) {
                    $permissions->deleteUsers[] = $userId;
                } else {
                    $permissions->deleteGroups[] = $userId;
                }
            }

            if ($access->hasPermission(DataboxExtraPermissionInterface::PERM_QUARANTINE)) {
                if ($isUser) {
                    $permissions->quarantineUsers[] = $userId;
                } else {
                    $permissions->quarantineGroups[] = $userId;
                }
            }
        }

        if (null !== $asset->getOwnerId()) {
            $permissions->users[] = $asset->getOwnerId();
        }

        $collectionsPaths = [];
        $stories = [];

        $workspaceInfo = $this->getWorkspaceHierarchyInfo($asset->getWorkspace());

        $permissions->mergeWith($workspaceInfo)->unique();

        foreach ($asset->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();
            $isReferenceCollection = $collection->getId() === $asset->getReferenceCollectionId();

            $collectionInfo = $this->getCollectionHierarchyInfo($collection);
            $collectionBestPrivacy = $collectionInfo->bestPrivacy;
            if (in_array($collectionBestPrivacy, [
                WorkspaceItemPrivacyInterface::PRIVATE,
                WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
            ], true)) {
                // Private collections does not expose its assets
                $collectionBestPrivacy = WorkspaceItemPrivacyInterface::SECRET;
            }
            $bestPrivacy = max($bestPrivacy, $collectionBestPrivacy);

            $permissions->mergeWith($collectionInfo->permissions, !$isReferenceCollection)->unique();

            if (null !== $storyAsset = $collection->getStoryAsset()) {
                $stories[] = $storyAsset->getId();

                $storyPermissions = $this->getAssetPermissionFields($storyAsset);

                $storyBestPrivacy = $storyPermissions->privacy;
                if (in_array($storyBestPrivacy, [
                    WorkspaceItemPrivacyInterface::PRIVATE,
                    WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                ], true)) {
                    // Private stories does not expose its assets
                    $storyBestPrivacy = WorkspaceItemPrivacyInterface::SECRET;
                }

                $bestPrivacy = max($bestPrivacy, $storyBestPrivacy);

                $permissions->mergeWith($storyPermissions->permissions, !$isReferenceCollection)->unique();

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

        return $this->workspaceCache->get($workspace->getId(), fn (): PermissionsDTO => $this->doGetWorkspaceHierarchyInfo($workspace));
    }

    private function doGetWorkspaceHierarchyInfo(Workspace $workspace): PermissionsDTO
    {
        $permissions = new PermissionsDTO();

        if (null !== $workspace->getOwnerId()) {
            $permissions->users[] = $workspace->getOwnerId();
        }

        $aces = $this->permissionManager->getObjectAces($workspace);
        foreach ($aces as $access) {
            $userId = $access->getUserId() ?? AccessControlEntryInterface::USER_WILDCARD;
            $isUser = AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType();
            if (
                $access->hasPermission(PermissionInterface::CHILD_VIEW)
                || $access->hasPermission(PermissionInterface::OWNER)
            ) {
                if ($isUser) {
                    $permissions->users[] = $userId;
                } else {
                    $permissions->groups[] = $userId;
                }
            }

            if ($access->hasPermission(PermissionInterface::CHILD_DELETE)) {
                if ($isUser) {
                    $permissions->deleteUsers[] = $userId;
                } else {
                    $permissions->deleteGroups[] = $userId;
                }
            }

            if ($access->hasPermission(DataboxExtraPermissionInterface::PERM_QUARANTINE)
                || $access->hasPermission(PermissionInterface::OWNER)) {
                if ($isUser) {
                    $permissions->quarantineUsers[] = $userId;
                } else {
                    $permissions->quarantineGroups[] = $userId;
                }
            }
        }

        return $permissions->unique();
    }

    private function getCollectionHierarchyInfo(Collection $collection): CollectionPermissionsDTO
    {
        if (null === $this->collectionCache) {
            return $this->doGetCollectionHierarchyInfo($collection);
        }

        return $this->collectionCache->get($collection->getId(), fn (): CollectionPermissionsDTO => $this->doGetCollectionHierarchyInfo($collection));
    }

    private function doGetCollectionHierarchyInfo(Collection $collection): CollectionPermissionsDTO
    {
        $bestPrivacyInParentHierarchy = $collection->getBestPrivacyInParentHierarchy();

        $permissions = new PermissionsDTO();

        if (!$collection->isStory() && null !== $collection->getOwnerId()) {
            $permissions->users[] = $collection->getOwnerId();
        }

        $aces = $this->permissionManager->getObjectAces($collection);
        foreach ($aces as $access) {
            $userId = $access->getUserId() ?? AccessControlEntryInterface::USER_WILDCARD;
            $isUser = AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType();
            if ($access->hasPermission(PermissionInterface::VIEW)
                || $access->hasPermission(PermissionInterface::CHILD_VIEW)) {
                if ($isUser) {
                    $permissions->users[] = $userId;
                } else {
                    $permissions->groups[] = $userId;
                }
            }

            if ($access->hasPermission(PermissionInterface::EDIT)
                || $access->hasPermission(PermissionInterface::DELETE)
                || $access->hasPermission(PermissionInterface::CHILD_DELETE)) {
                if ($isUser) {
                    $permissions->deleteUsers[] = $userId;
                } else {
                    $permissions->deleteGroups[] = $userId;
                }
            }

            if (in_array(DataboxExtraPermissionInterface::PERM_QUARANTINE, $access->getMetadata(), true)) {
                if ($isUser) {
                    $permissions->quarantineUsers[] = $userId;
                } else {
                    $permissions->quarantineGroups[] = $userId;
                }
            }
        }

        if (null !== $parent = $collection->getParent()) {
            $parentInfo = $this->getCollectionHierarchyInfo($parent);

            $permissions->mergeWith($parentInfo->permissions, false)->unique();
        }

        return new CollectionPermissionsDTO(
            $bestPrivacyInParentHierarchy,
            $collection->getAbsolutePath(),
            $permissions->unique(),
        );
    }
}
