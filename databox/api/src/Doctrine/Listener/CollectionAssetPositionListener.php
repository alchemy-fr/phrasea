<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Core\CollectionAsset;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Appends every new relation at the end of its collection, whatever creates it
 * (API, import, copy, move, workflow…), unless a position was set explicitly.
 */
#[AsDoctrineListener(Events::prePersist)]
#[AsDoctrineListener(Events::postFlush)]
class CollectionAssetPositionListener
{
    /**
     * Next free position per collection, so that several relations persisted
     * before a single flush do not all land on the same rank.
     *
     * @var array<string, int>
     */
    private array $nextPositions = [];

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof CollectionAsset || null !== $entity->getPosition()) {
            return;
        }

        $collectionId = $entity->getCollection()->getId();

        if (!isset($this->nextPositions[$collectionId])) {
            $maxPosition = $args->getObjectManager()
                ->getRepository(CollectionAsset::class)
                ->getMaxPosition($collectionId);
            $this->nextPositions[$collectionId] = null === $maxPosition ? 0 : $maxPosition + 1;
        }

        $entity->setPosition($this->nextPositions[$collectionId]++);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->nextPositions = [];
    }
}
