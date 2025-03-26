<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Doctrine\SoftDeleteToggler;
use App\Elasticsearch\IndexCleaner;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Template\AssetDataTemplate;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CollectionDelete
{
    public function __construct(
        private EntityManagerInterface $em,
        private IndexCleaner $indexCleaner,
        private SoftDeleteToggler $softDeleteToggler,
    ) {
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

        /** @var Collection $collection */
        $collection = $this->em->find(Collection::class, $collectionId);
        if (!$collection instanceof Collection) {
            throw new \InvalidArgumentException(sprintf('Collection "%s" not found for deletion', $collectionId));
        }

        if($collection->isStory()) {
            $storyAsset = $collection->getStoryAsset();
            $storyAsset->setStoryCollection(null);
            $this->em->remove($storyAsset);
            $collection->setStoryAsset(null);
            $this->em->flush();
        }

        $assets = $this->em->getRepository(Asset::class)
            ->createQueryBuilder('t')
            ->select('t.id')
            ->andWhere('t.referenceCollection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->toIterable();

        $this->em->getRepository(CollectionAsset::class)
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.collection = :c')
            ->setParameter('c', $collectionId)
            ->getQuery()
            ->execute();

        foreach ($assets as $a) {
            $asset = $this->em->find(Asset::class, $a['id']);
            $this->em->remove($asset);
            $this->em->flush();
            $this->em->clear();
        }

        $this->deleteDependencies(AssetDataTemplate::class, $collectionId);

        $collection = $this->em->find(Collection::class, $collectionId);
        if ($collection instanceof Collection) {
            $this->em->remove($collection);
            $this->em->flush();
        }
    }

    private function deleteDependencies(string $entityClass, string $collectionId): void
    {
        $items = $this->em->getRepository($entityClass)->findBy([
            'collection' => $collectionId,
        ]);
        foreach ($items as $item) {
            $this->em->remove($item);
        }
        $this->em->flush();
        $this->em->clear();
    }
}
