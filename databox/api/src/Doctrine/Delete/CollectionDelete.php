<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use App\Doctrine\SoftDeleteToggler;
use App\Elasticsearch\IndexCleaner;
use App\Elasticsearch\Listener\DeferredIndexListener;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Throwable;

class CollectionDelete
{
    private EntityManagerInterface $em;
    private IndexCleaner $indexCleaner;
    private SoftDeleteToggler $softDeleteToggler;

    public function __construct(EntityManagerInterface $em, IndexCleaner $indexCleaner, SoftDeleteToggler $softDeleteToggler)
    {
        $this->em = $em;
        $this->indexCleaner = $indexCleaner;
        $this->softDeleteToggler = $softDeleteToggler;
    }

    public function deleteCollection(string $collectionId, bool $isChildProcess = false): void
    {
        if (!$isChildProcess) {
            $this->softDeleteToggler->disable();
            $collection = $this->em->find(Collection::class, $collectionId);
            if (!$collection instanceof Collection) {
                throw new InvalidArgumentException(sprintf('Collection "%s" not found for deletion', $collectionId));
            }
            if (null === $collection->getDeletedAt()) {
                throw new InvalidArgumentException(sprintf('Collection "%s" is not marked as deleted', $collection->getId()));
            }

            $this->indexCleaner->removeCollectionFromIndex($collectionId);
            DeferredIndexListener::disable();
            $configuration = $this->em->getConnection()->getConfiguration();
            $logger = $configuration->getSQLLogger();
            $configuration->setSQLLogger(null);

            $this->em->beginTransaction();
            try {
                $this->doDelete($collectionId);
                $this->em->commit();
            } catch (Throwable $e) {
                $this->em->rollback();
                throw $e;
            } finally {
                $this->softDeleteToggler->enable();
                DeferredIndexListener::enable();
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
            $this->doDelete($c['id']);
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

        $collection = $this->em->find(Collection::class, $collectionId);
        if ($collection instanceof Collection) {
            dump('DELETE FROM collection => '.$collectionId);
            $this->em->remove($collection);
            $this->em->flush();
        }
    }
}
