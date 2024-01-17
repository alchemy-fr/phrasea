<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CollectionPostTransformListener implements EventSubscriberInterface
{
    public function __construct(private PermissionManager $permissionManager)
    {
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Collection $collection */
        if (!($collection = $event->getObject()) instanceof Collection) {
            return;
        }

        $document = $event->getDocument();

        $bestPrivacy = $collection->getBestPrivacyInDescendantHierarchy();

        [$users, $groups] = $this->discoverChildren($collection);

        if (!in_array(null, $users, true)) {
            $parent = $collection->getParent();
            while (null !== $parent) {
                $users = array_merge($users, $this->permissionManager->getAllowedUsers($parent, PermissionInterface::VIEW));
                if (in_array(null, $users, true)) {
                    break;
                }

                $groups = array_merge($groups, $this->permissionManager->getAllowedGroups($parent, PermissionInterface::VIEW));
                $parent = $parent->getParent();
            }
        }

        if (in_array(null, $users, true)) {
            $users = ['*'];
            $groups = [];
            $bestPrivacy = max($bestPrivacy, WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS);
        }

        $document->set('hasChildren', !$collection->getChildren()->isEmpty());
        $document->set('privacy', $bestPrivacy);
        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
    }

    private function discoverChildren(Collection $collection): array
    {
        $users = [];
        if (null !== $collection->getOwnerId()) {
            $users[] = $collection->getOwnerId();
        }

        $users = array_merge($users, $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW));
        $groups = $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW);

        foreach ($collection->getChildren() as $child) {
            [$u, $g] = $this->discoverChildren($child);
            $users = array_merge($users, $u);
            $groups = array_merge($groups, $g);
        }

        return [array_values(array_unique($users)), array_values(array_unique($groups))];
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
