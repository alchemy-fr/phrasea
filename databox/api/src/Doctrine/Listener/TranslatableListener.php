<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\TranslatableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::prePersist)]
readonly class TranslatableListener implements EventSubscriber
{
    public function __construct(private string $defaultLocale)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof TranslatableInterface) {
            if (!$object->hasLocale()) {
                $object->setLocale($this->defaultLocale);
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
        ];
    }
}
