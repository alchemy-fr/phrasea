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

        $bestPrivacy = $collection->getPrivacy();

        $users = $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW);

        // "nl" stands for Next Level and means permissions for sets which have access to a sub folder only (not the root one)
        $nlUsers = $users;
        $nlGroups = $groups;

        $parent = $collection->getParent();
        while (null !== $parent) {
            $bestPrivacy = max($bestPrivacy, $parent->getPrivacy());
            if ($bestPrivacy >= WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS) {
                $nlUsers = [];
                $nlGroups = [];
                break;
            }

            $parentUsers = $this->permissionManager->getAllowedUsers($parent, PermissionInterface::VIEW);
            $users = array_merge($users, $parentUsers);
            $nlUsers = array_diff($nlUsers, $parentUsers);

            if (in_array(null, $users, true)) {
                $nlUsers = [];
                $nlGroups = [];
                $bestPrivacy = max($bestPrivacy, WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS);
                break;
            }

            $parentGroups = $this->permissionManager->getAllowedGroups($parent, PermissionInterface::VIEW);
            $groups = array_merge($groups, $parentGroups);
            $nlGroups = array_diff($nlGroups, $parentGroups);

            $parent = $parent->getParent();
        }

        if (in_array(null, $users, true)) {
            $users = [];
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
