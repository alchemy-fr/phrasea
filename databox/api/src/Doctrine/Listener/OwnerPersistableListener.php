<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Listener\OwnerPersistableInterface;
use App\Security\OwnerAssigner;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;

/**
 * Owner of the entities created outside of the API (the API assigns it before
 * validation, see OwnerAssignmentProvider).
 */
#[AsDoctrineListener(event: 'prePersist')]
final readonly class OwnerPersistableListener
{
    public function __construct(
        private OwnerAssigner $ownerAssigner,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof OwnerPersistableInterface) {
            $this->ownerAssigner->assignIfMissing($entity);
        }
    }
}
