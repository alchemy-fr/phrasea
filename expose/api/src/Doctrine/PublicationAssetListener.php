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
        ];
    }
}
