<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CollectionPostTransformListener implements EventSubscriberInterface
{
    private PermissionManager $permissionManager;

    public function __construct(PermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Collection $collection */
        if (!($collection = $event->getObject()) instanceof Collection) {
            return;
        }

        $document = $event->getDocument();

        $isPublic = $collection->isPublicOrHasPublicParent();

        $users = $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW);

        if (null !== $collection->getOwnerId()) {
            $users[] = $collection->getOwnerId();
        }

        $document->set('public', $isPublic);
        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }

}
