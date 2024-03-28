<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Basket\Basket;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class BasketPostTransformListener implements EventSubscriberInterface
{
    public function __construct(private PermissionManager $permissionManager)
    {
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Basket $basket */
        if (!($basket = $event->getObject()) instanceof Basket) {
            return;
        }

        $document = $event->getDocument();
        $users = $this->permissionManager->getAllowedUsers($basket, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($basket, PermissionInterface::VIEW);
        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
