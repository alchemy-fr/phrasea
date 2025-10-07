<?php

namespace App\Elasticsearch;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
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

            $users = $this->permissionManager->getAllowedUsers($asset, PermissionInterface::VIEW);
            $groups = $this->permissionManager->getAllowedGroups($asset, PermissionInterface::VIEW);

            if (null !== $asset->getOwnerId()) {
                $users[] = $asset->getOwnerId();
            }

            $collectionsPaths = [];
            $stories = [];
            foreach ($asset->getCollections() as $collectionAsset) {
                $collection = $collectionAsset->getCollection();

                [$cBestPrivacy, $absolutePath, $cUsers, $cGroups] = $this->getCollectionHierarchyInfo($collection);

                if (in_array($cBestPrivacy, [
                    WorkspaceItemPrivacyInterface::PRIVATE,
                    WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                ], true)) {
                    // Private collections does not expose its assets
                    $cBestPrivacy = WorkspaceItemPrivacyInterface::SECRET;
                }
                $bestPrivacy = max($bestPrivacy, $cBestPrivacy);

                $collectionsPaths[] = $absolutePath;
                $users = array_merge($users, $cUsers);
                $groups = array_merge($groups, $cGroups);

                if (null !== $storyAsset = $collection->getStoryAsset()) {
                    $stories[] = $storyAsset->getId();

                    $storyPermissions = $this->getAssetPermissionFields($storyAsset);

                    $bestPrivacy = max($bestPrivacy, $storyPermissions->privacy);
                    $users = array_merge($users, $storyPermissions->users);
                    $groups = array_merge($groups, $storyPermissions->groups);
                    $collectionsPaths = array_merge($collectionsPaths, $storyPermissions->collectionPaths);
                }

            }

            return new AssetPermissionsDTO(
                $bestPrivacy,
                array_values(array_unique($users)),
                array_values(array_unique($groups)),
                array_values(array_unique($collectionsPaths)),
                array_values(array_unique($stories)),
            );
        });
    }

    private function getCollectionHierarchyInfo(Collection $collection): array
    {
        return $this->collectionCache->get($collection->getId(), function () use ($collection): array {
            $bestPrivacyInParentHierarchy = $collection->getBestPrivacyInParentHierarchy();
            $cUsers = [];
            $cGroups = [];

            if ($bestPrivacyInParentHierarchy < WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS) {
                if (!$collection->isStory() && null !== $collection->getOwnerId()) {
                    $cUsers[] = $collection->getOwnerId();
                }

                $cUsers = array_merge($cUsers, $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW));
                $cGroups = array_merge($cGroups, $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW));

                if (null !== $parent = $collection->getParent()) {
                    [, , $pUsers, $pGroups] = $this->getCollectionHierarchyInfo($parent);
                    $cUsers = array_merge($cUsers, $pUsers);
                    $cGroups = array_merge($cGroups, $pGroups);
                }
            }

            return [
                $bestPrivacyInParentHierarchy,
                $collection->getAbsolutePath(),
                array_values(array_unique($cUsers)),
                array_values(array_unique($cGroups)),
            ];
        });
    }
}
