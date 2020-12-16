<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\PublicationAsset;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;

class PublicationAssetListener implements EventSubscriber
{
    private array $positionCache = [];

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof PublicationAsset) {
            $em = $args->getObjectManager();
            if ($this->isAssetOrphan($em, $entity)) {
                $em->remove($entity->getAsset());
            }
        }
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof PublicationAsset) {
            if (0 === $entity->getPosition()) {
                $em = $args->getObjectManager();

                $pos = $this->getNextPublicationAssetPosition(
                    $em,
                    $entity->getPublication()->getId()
                );
                $entity->setPosition($pos);
            }
        }
    }

    /**
     * @param EntityManagerInterface $em
     */
    public function getNextPublicationAssetPosition(ObjectManager $em, string $publicationId): int
    {
        if (isset($this->positionCache[$publicationId])) {
            return ++$this->positionCache[$publicationId];
        }

        $position = $em->createQueryBuilder()
            ->select('MAX(pa.position)')
            ->from(PublicationAsset::class, 'pa')
            ->andWhere('pa.publication = :p')
            ->setParameter('p', $publicationId)
            ->getQuery()
            ->getSingleScalarResult();

        $this->positionCache[$publicationId] = $position ?? 0;

        return ++$this->positionCache[$publicationId];
    }

    /**
     * @param EntityManagerInterface $em
     */
    public function isAssetOrphan(ObjectManager $em, PublicationAsset $publicationAsset): bool
    {
        $assoc = $em->createQueryBuilder()
            ->select('COUNT(pa.id)')
            ->from(PublicationAsset::class, 'pa')
            ->andWhere('pa.asset = :asset')
            ->andWhere('pa.id != :id')
            ->setParameter('asset', $publicationAsset->getAsset()->getId())
            ->setParameter('id', $publicationAsset->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $assoc === 0;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
            Events::prePersist,
        ];
    }
}
