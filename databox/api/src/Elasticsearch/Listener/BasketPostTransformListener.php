<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
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
        // An ACE given to all users (null) is indexed under the wildcard, which BasketSearch matches
        $toTerms = static fn (array $ids): array => array_values(array_unique(array_map(
            static fn (?string $id): string => $id ?? AccessControlEntryInterface::USER_WILDCARD,
            $ids,
        )));
        $document->set('users', $toTerms($this->permissionManager->getAllowedUsers($basket, PermissionInterface::VIEW)));
        $document->set('groups', $toTerms($this->permissionManager->getAllowedGroups($basket, PermissionInterface::VIEW)));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
