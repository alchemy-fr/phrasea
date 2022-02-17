<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use App\Doctrine\SoftDeleteToggler;
use App\Elasticsearch\IndexCleaner;
use App\Elasticsearch\Listener\DeferredIndexListener;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Throwable;

class WorkspaceDelete
{
    private EntityManagerInterface $em;
    private CollectionDelete $collectionDelete;
    private IndexCleaner $indexCleaner;
    private SoftDeleteToggler $softDeleteToggler;

    public function __construct(
        EntityManagerInterface $em,
        CollectionDelete $collectionDelete,
        IndexCleaner $indexCleaner,
        SoftDeleteToggler $softDeleteToggler
    )
    {
        $this->em = $em;
        $this->collectionDelete = $collectionDelete;
        $this->indexCleaner = $indexCleaner;
        $this->softDeleteToggler = $softDeleteToggler;
    }

    public function deleteWorkspace(string $workspaceId): void
    {
        $this->softDeleteToggler->disable();
        try {
            $workspace = $this->em->find(Workspace::class, $workspaceId);
            if (!$workspace instanceof Workspace) {
                throw new InvalidArgumentException(sprintf('Workspace "%s" not found for deletion', $workspaceId));
            }
            if (null === $workspace->getDeletedAt()) {
                throw new InvalidArgumentException(sprintf('Workspace "%s" is not marked as deleted', $workspace->getId()));
            }

            $this->indexCleaner->removeWorkspaceFromIndex($workspaceId);
            DeferredIndexListener::disable();

            $this->em->beginTransaction();

            $configuration = $this->em->getConnection()->getConfiguration();
            $logger = $configuration->getSQLLogger();
            $configuration->setSQLLogger(null);
            try {
                $collections = $this->em->getRepository(Collection::class)
                    ->createQueryBuilder('t')
                    ->select('t.id')
                    ->andWhere('t.parent IS NULL')
                    ->andWhere('t.workspace = :ws')
                    ->setParameter('ws', $workspaceId)
                    ->getQuery()
                    ->toIterable();

                foreach ($collections as $c) {
                    $this->collectionDelete->deleteCollection((string) $c['id'], true);
                }

                $this->deleteDependencies(Tag::class, $workspaceId);
                $this->deleteDependencies(RenditionDefinition::class, $workspaceId);
                $this->deleteDependencies(RenditionClass::class, $workspaceId);
                $this->deleteDependencies(AttributeDefinition::class, $workspaceId);

                $files = $this->em->getRepository(File::class)
                    ->createQueryBuilder('t')
                    ->select('t.id')
                    ->andWhere('t.workspace = :ws')
                    ->setParameter('ws', $workspaceId)
                    ->getQuery()
                    ->toIterable();

                foreach ($files as $f) {
                    $workspace = $this->em->find(File::class, $f['id']);
                    $this->em->remove($workspace);
                    $this->em->flush();
                }

                $workspace = $this->em->find(Workspace::class, $workspaceId);
                $this->em->remove($workspace);
                $this->em->flush();
                $this->em->commit();
            } catch (Throwable $e) {
                $this->em->rollback();
                throw $e;
            } finally {
                DeferredIndexListener::enable();
                $configuration->setSQLLogger($logger);
            }
        } finally {
            $this->softDeleteToggler->enable();
        }
    }

    private function deleteDependencies(string $entityClass, string $workspaceId): void
    {
        $items = $this->em->getRepository($entityClass)->findBy([
            'workspace' => $workspaceId,
        ]);
        foreach ($items as $item) {
            $this->em->remove($item);
        }
        $this->em->flush();
        $this->em->clear();
    }
}
