<?php

namespace App\Doctrine\Listener;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Listener\OwnerPersistableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsDoctrineListener(event: 'prePersist')]
#[AsEventListener(event: KernelEvents::VIEW, method: 'preValidate', priority: EventPriorities::PRE_VALIDATE)]
final readonly class OwnerPersistableListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function preValidate(ViewEvent $event): void
    {
        $this->handleEntity($event->getControllerResult());
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->handleEntity($args->getObject());
    }

    private function handleEntity($entity): void
    {
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
