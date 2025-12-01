<?php

namespace App\Elasticsearch;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Elasticsearch\Listener\Dto\AssetPermissionsDTO;
use App\Elasticsearch\Listener\Dto\CollectionPermissionsDTO;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Contracts\Cache\CacheInterface;

final class AssetPermissionComputer
{
    private CacheInterface $collectionCache;
    private CacheInterface $assetCache;

    public function __construct(
        private readonly PermissionManager $permissionManager,
    ) {
        $this->disableAssetCache();
        $this->disableCollectionCache();
    }

    public function setCollectionCache(CacheInterface $collectionCache): void
    {
        $this->collectionCache = $collectionCache;
    }

    public function clearCollectionCache(): void
    {
        $this->collectionCache->clear();
    }

    public function clearAssetCache(): void
    {
        $this->assetCache->clear();
    }

    public function setAssetCache(CacheInterface $assetCache): void
    {
        $this->assetCache = $assetCache;
    }

    public function disableCollectionCache(): void
    {
        $this->collectionCache = new NullAdapter();
    }

    public function disableAssetCache(): void
    {
        $this->assetCache = new NullAdapter();
    }

    public function getAssetPermissionFields(Asset $asset): AssetPermissionsDTO
    {
        return $this->assetCache->get($asset->getId(), function () use ($asset): AssetPermissionsDTO {
            $bestPrivacy = $asset->getPrivacy();

            $users = [];
            $groups = [];
            $deleteUsers = [];
            $deleteGroups = [];

            $aces = $this->permissionManager->getObjectAces($asset);
            foreach ($aces as $access) {
                $userId = $access->getUserId();
                $isUser = AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType();
                if ($access->hasPermission(PermissionInterface::VIEW)) {
                    if ($isUser) {
                        $users[] = $userId;
                    } else {
                        $groups[] = $userId;
                    }
                }

                if ($access->hasPermission(PermissionInterface::DELETE)) {
                    if ($isUser) {
                        $deleteUsers[] = $userId;
                    } else {
                        $deleteGroups[] = $userId;
                    }
                }
            }

            if (null !== $asset->getOwnerId()) {
                $users[] = $asset->getOwnerId();
            }

            $collectionsPaths = [];
            $stories = [];
            foreach ($asset->getCollections() as $collectionAsset) {
                $collection = $collectionAsset->getCollection();

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

                $users = array_merge($users, $collectionInfo->users);
                $groups = array_merge($groups, $collectionInfo->groups);

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
                    $users = array_merge($users, $storyPermissions->users);
                    $groups = array_merge($groups, $storyPermissions->groups);
                    $deleteUsers = array_merge($deleteUsers, $storyPermissions->deleteUsers);
                    $deleteGroups = array_merge($deleteGroups, $storyPermissions->deleteGroups);
                    $collectionsPaths = array_merge($collectionsPaths, $storyPermissions->collectionPaths);
                } else {
                    $collectionsPaths[] = $collectionInfo->absolutePath;
                }
            }

            return new AssetPermissionsDTO(
                $bestPrivacy,
                array_values(array_unique($users)),
                array_values(array_unique($groups)),
                array_values(array_unique($deleteUsers)),
                array_values(array_unique($deleteGroups)),
                array_values(array_unique($collectionsPaths)),
                array_values(array_unique($stories)),
            );
        });
    }

    private function getCollectionHierarchyInfo(Collection $collection): CollectionPermissionsDTO
    {
        return $this->collectionCache->get($collection->getId(), function () use ($collection): CollectionPermissionsDTO {
            $bestPrivacyInParentHierarchy = $collection->getBestPrivacyInParentHierarchy();
            $users = [];
            $groups = [];
            $deleteUsers = [];
            $deleteGroups = [];

            if ($bestPrivacyInParentHierarchy < WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS) {
                if (!$collection->isStory() && null !== $collection->getOwnerId()) {
                    $users[] = $collection->getOwnerId();
                }

                $aces = $this->permissionManager->getObjectAces($collection);
                foreach ($aces as $access) {
                    $userId = $access->getUserId();
                    $isUser = AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType();
                    if ($access->hasPermission(PermissionInterface::VIEW)) {
                        if ($isUser) {
                            $users[] = $userId;
                        } else {
                            $groups[] = $userId;
                        }
                    }

                    if ($access->hasPermission(PermissionInterface::EDIT)
                        || $access->hasPermission(PermissionInterface::DELETE)) {
                        if ($isUser) {
                            $deleteUsers[] = $userId;
                        } else {
                            $deleteGroups[] = $userId;
                        }
                    }
                }

                if (null !== $parent = $collection->getParent()) {
                    $parentInfo = $this->getCollectionHierarchyInfo($parent);
                    $users = array_merge($users, $parentInfo->users);
                    $groups = array_merge($groups, $parentInfo->groups);
                    $deleteUsers = array_merge($deleteUsers, $parentInfo->deleteUsers);
                    $deleteGroups = array_merge($deleteGroups, $parentInfo->deleteGroups);
                }
            }

            return new CollectionPermissionsDTO(
                $bestPrivacyInParentHierarchy,
                $collection->getAbsolutePath(),
                array_values(array_unique($users)),
                array_values(array_unique($groups)),
                array_values(array_unique($deleteUsers)),
                array_values(array_unique($deleteGroups)),
            );
        });
    }
}
