<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetPostTransformListener implements EventSubscriberInterface
{
    private PermissionManager $permissionManager;

    public function __construct(PermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Asset $asset */
        if (!($asset = $event->getObject()) instanceof Asset) {
            return;
        }

        $document = $event->getDocument();

        $bestPrivacy = $asset->getPrivacy();

        $users = $this->permissionManager->getAllowedUsers($asset, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($asset, PermissionInterface::VIEW);
        // Check ACE on asset

        if (null !== $asset->getOwnerId()) {
            $users[] = $asset->getOwnerId();
        }

        $collectionsPaths = [];
        foreach ($asset->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();

            if (($collBestPrivacy = $collection->getBestPrivacyInHierarchy()) > $bestPrivacy) {
                $bestPrivacy = $collBestPrivacy;
            }

            if (null !== $collection->getOwnerId()) {
                $users[] = $collection->getOwnerId();
            }

            if ($bestPrivacy < WorkspaceItemPrivacyInterface::PUBLIC) {
                $users = array_merge($users, $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW));
                $groups = array_merge($groups, $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW));
            }

            if ($collection->isPublicOrHasPublicParent()) {
                $bestPrivacy = true;
            }

            $collectionsPaths[] = $collection->getAbsolutePath();
        }

        $document->set('privacy', $bestPrivacy);
        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
        $document->set('collectionPaths', $collectionsPaths);
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }

}
