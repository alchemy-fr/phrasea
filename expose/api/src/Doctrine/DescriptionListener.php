<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Asset;
use App\Entity\Publication;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use HTMLPurifier;

class DescriptionListener implements EventSubscriber
{
    private HTMLPurifier $purifier;

    public function __construct(HTMLPurifier $purifier)
    {
        $this->purifier = $purifier;
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->handle($args);
    }

    private function handle(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Publication
            || $entity instanceof Asset
        ) {
            $entity->setDescription($this->cleanHtml($entity->getDescription()));
        }
    }

    private function cleanHtml(?string $data): ?string
    {
        if (null === $data) {
            return null;
        }

        return $this->purifier->purify($data);
    }

    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }
}
