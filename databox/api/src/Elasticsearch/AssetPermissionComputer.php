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
    private CacheInterface $cache;

    public function __construct(
        private readonly PermissionManager $permissionManager,
    ) {
        $this->disableCache();
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function disableCache(): void
    {
        $this->cache = new NullAdapter();
    }

    public function getAssetPermissionFields(Asset $asset): array
    {
        $bestPrivacy = $asset->getPrivacy();

        $users = $this->permissionManager->getAllowedUsers($asset, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($asset, PermissionInterface::VIEW);

        if (null !== $asset->getOwnerId()) {
            $users[] = $asset->getOwnerId();
        }

        $collectionsPaths = [];
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
        }

        return [
            'privacy' => $bestPrivacy,
            'users' => array_values(array_unique($users)),
            'groups' => array_values(array_unique($groups)),
            'collectionPaths' => array_unique($collectionsPaths),
        ];
    }


    private function getCollectionHierarchyInfo(Collection $collection): array
    {
        return $this->cache->get($collection->getId(), function () use ($collection): array {
            $bestPrivacyInParentHierarchy = $collection->getBestPrivacyInParentHierarchy();
            $cUsers = [];
            $cGroups = [];

            if ($bestPrivacyInParentHierarchy < WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS) {
                if (null !== $collection->getOwnerId()) {
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
