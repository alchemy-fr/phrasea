<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use App\Doctrine\SoftDeleteToggler;
use App\Elasticsearch\IndexCleaner;
use App\Elasticsearch\Listener\DeferredIndexListener;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CollectionDelete
{
    public function __construct(private EntityManagerInterface $em, private IndexCleaner $indexCleaner, private SoftDeleteToggler $softDeleteToggler)
    {
    }

    public function deleteCollection(string $collectionId, bool $isChildProcess = false): void
    {
        if (!$isChildProcess) {
            $collection = $this->em->find(Collection::class, $collectionId);
            if (!$collection instanceof Collection) {
                throw new \InvalidArgumentException(sprintf('Collection "%s" not found for deletion', $collectionId));
            }
            if (null === $collection->getDeletedAt()) {
                throw new \InvalidArgumentException(sprintf('Collection "%s" is not marked as deleted', $collection->getId()));
            }

            $this->indexCleaner->removeCollectionFromIndex($collectionId);

            DeferredIndexListener::disable();
            $this->softDeleteToggler->disable();

            $this->em->beginTransaction();

            $configuration = $this->em->getConnection()->getConfiguration();
            $logger = $configuration->getSQLLogger();
            $configuration->setSQLLogger();

            try {
                $this->doDelete($collectionId);
                $this->em->commit();
            } catch (\Throwable $e) {
                $this->em->rollback();
                throw $e;
            } finally {
                DeferredIndexListener::enable();
                $this->softDeleteToggler->enable();
                $configuration->setSQLLogger($logger);
            }
        } else {
            $this->doDelete($collectionId);
        }
    }

    private function doDelete(string $collectionId): void
    {
        $children = $this->em->getRepository(Collection::class)
            ->createQueryBuilder('t')
            ->select('t.id')
            ->andWhere('t.parent = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->toIterable();

        foreach ($children as $c) {
            $this->doDelete((string) $c['id']);
            $this->em->clear();
        }

        $assets = $this->em->getRepository(Asset::class)
            ->createQueryBuilder('t')
            ->select('t.id')
            ->andWhere('t.referenceCollection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->toIterable();

        foreach ($assets as $a) {
            $asset = $this->em->find(Asset::class, $a['id']);
            $this->em->remove($asset);
            $this->em->flush();
            $this->em->clear();
        }

        $this->em->getRepository(CollectionAsset::class)
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.collection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->execute();

        $collection = $this->em->find(Collection::class, $collectionId);
        if ($collection instanceof Collection) {
            $this->em->remove($collection);
            $this->em->flush();
        }
    }
}
