<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Core\Asset;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetPostTransformListener implements EventSubscriberInterface
{
    private PermissionManager $permissionManager;

    public function __construct(PermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    public function hydrateAssetDocument(PostTransformEvent $event): void
    {
        /** @var Asset $asset */
        if (!($asset = $event->getObject()) instanceof Asset) {
            return;
        }

        $document = $event->getDocument();

        $isPublic = $asset->isPublic();

        $users = $this->permissionManager->getAllowedUsers($asset, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($asset, PermissionInterface::VIEW);
        // Check ACE on asset

        if (null !== $asset->getOwnerId()) {
            $users[] = $asset->getOwnerId();
        }

        $collectionsPaths = [];
        foreach ($asset->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();
            if ($collection->isPublic()) {
                $isPublic = true;
            }

            if (null !== $collection->getOwnerId()) {
                $users[] = $collection->getOwnerId();
            }

            if (!$isPublic) {
                $users = array_merge($users, $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW));
                $groups = array_merge($groups, $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW));
            }

            if ($collection->isPublicOrHasPublicParent()) {
                $isPublic = true;
            }

            $collectionsPaths[] = $collection->getAbsolutePath();
        }

        $document->set('public', $isPublic);
        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
        $document->set('collectionPaths', $collectionsPaths);
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateAssetDocument',
        ];
    }

}
