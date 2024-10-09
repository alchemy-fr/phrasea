<?php

namespace App\Doctrine\Listener;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Listener\OwnerPersistableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: 'prePersist')]
final readonly class OwnerPersistableListener
{
    public function __construct(
        private Security $security,
    )
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof OwnerPersistableInterface) {
            if (null === $entity->getOwnerId()) {
                $user = $this->security->getUser();
                if ($user instanceof JwtUser) {
                    $entity->setOwnerId($user->getId());
                }
            }
        }
    }
}
