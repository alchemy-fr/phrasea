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

            $aces = $this->permissionManager->getObjectAces($asset);
            $users = [];
            $groups = [];
            $deleteUsers = [];
            $deleteGroups = [];
            foreach ($aces as $access) {
                if (AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType()) {
                    if ($access->hasPermission(PermissionInterface::VIEW)) {
                        $users[] = $access->getUserId();
                    }
                    if ($access->hasPermission(PermissionInterface::DELETE)) {
                        $deleteUsers[] = $access->getUserId();
                    }
                } elseif (AccessControlEntryInterface::TYPE_GROUP_VALUE === $access->getUserType()) {
                    if ($access->hasPermission(PermissionInterface::VIEW)) {
                        $groups[] = $access->getUserId();
                    }
                    if ($access->hasPermission(PermissionInterface::DELETE)) {
                        $deleteGroups[] = $access->getUserId();
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
                $cBestPrivacy = $collectionInfo->bestPrivacy;
                if (in_array($cBestPrivacy, [
                    WorkspaceItemPrivacyInterface::PRIVATE,
                    WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                ], true)) {
                    // Private collections does not expose its assets
                    $cBestPrivacy = WorkspaceItemPrivacyInterface::SECRET;
                }
                $bestPrivacy = max($bestPrivacy, $cBestPrivacy);

                $collectionsPaths[] = $collectionInfo->absolutePath;
                $users = array_merge($users, $collectionInfo->users);
                $groups = array_merge($groups, $collectionInfo->groups);

                if (null !== $storyAsset = $collection->getStoryAsset()) {
                    $stories[] = $storyAsset->getId();

                    $storyPermissions = $this->getAssetPermissionFields($storyAsset);

                    $bestPrivacy = max($bestPrivacy, $storyPermissions->privacy);
                    $users = array_merge($users, $storyPermissions->users);
                    $groups = array_merge($groups, $storyPermissions->groups);
                    $deleteUsers = array_merge($deleteUsers, $storyPermissions->deleteUsers);
                    $deleteGroups = array_merge($deleteGroups, $storyPermissions->deleteGroups);
                    $collectionsPaths = array_merge($collectionsPaths, $storyPermissions->collectionPaths);
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
            $cUsers = [];
            $cGroups = [];
            $cDeleteUsers = [];
            $cDeleteGroups = [];

            if ($bestPrivacyInParentHierarchy < WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS) {
                if (!$collection->isStory() && null !== $collection->getOwnerId()) {
                    $cUsers[] = $collection->getOwnerId();
                }

                $aces = $this->permissionManager->getObjectAces($collection);
                foreach ($aces as $access) {
                    if (AccessControlEntryInterface::TYPE_USER_VALUE === $access->getUserType()) {
                        if ($access->hasPermission(PermissionInterface::VIEW)) {
                            $cUsers[] = $access->getUserId();
                        }
                        if ($access->hasPermission(PermissionInterface::EDIT)) {
                            $cDeleteUsers[] = $access->getUserId();
                        }
                    } elseif (AccessControlEntryInterface::TYPE_GROUP_VALUE === $access->getUserType()) {
                        if ($access->hasPermission(PermissionInterface::EDIT)) {
                            $cGroups[] = $access->getUserId();
                        }
                        if ($access->hasPermission(PermissionInterface::DELETE)) {
                            $cDeleteGroups[] = $access->getUserId();
                        }
                    }
                }

                if (null !== $parent = $collection->getParent()) {
                    $parentInfo = $this->getCollectionHierarchyInfo($parent);
                    $cUsers = array_merge($cUsers, $parentInfo->users);
                    $cGroups = array_merge($cGroups, $parentInfo->groups);
                    $cDeleteUsers = array_merge($cUsers, $parentInfo->deleteUsers);
                    $cDeleteGroups = array_merge($cGroups, $parentInfo->deleteGroups);
                }
            }

            return new CollectionPermissionsDTO(
                $bestPrivacyInParentHierarchy,
                $collection->getAbsolutePath(),
                array_values(array_unique($cUsers)),
                array_values(array_unique($cGroups)),
                array_values(array_unique($cDeleteUsers)),
                array_values(array_unique($cDeleteGroups)),
            );
        });
    }
}
