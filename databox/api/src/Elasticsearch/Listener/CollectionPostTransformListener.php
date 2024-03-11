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

        $bestPrivacy = $collection->getBestPrivacyInParentHierarchy();

        $users = $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW);

        // "nl" stands for Next Level and means permissions for sets which have access to a sub folder only (not the root one)
        $nlUsers = $users;
        $nlGroups = $groups;

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
        $document->set('nlUsers', array_values(array_unique($nlUsers)));
        $document->set('nlGroups', array_values(array_unique($nlGroups)));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
