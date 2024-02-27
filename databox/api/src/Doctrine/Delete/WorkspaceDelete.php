<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Doctrine\SoftDeleteToggler;
use App\Elasticsearch\IndexCleaner;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\File;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

class WorkspaceDelete
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly CollectionDelete $collectionDelete, private readonly IndexCleaner $indexCleaner, private readonly SoftDeleteToggler $softDeleteToggler)
    {
    }

    public function deleteWorkspace(string $workspaceId): void
    {
        $workspace = $this->em->find(Workspace::class, $workspaceId);
        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException(sprintf('Workspace "%s" not found for deletion', $workspaceId));
        }
        if (null === $workspace->getDeletedAt()) {
            throw new \InvalidArgumentException(sprintf('Workspace "%s" is not marked as deleted', $workspace->getId()));
        }

        $this->indexCleaner->removeWorkspaceFromIndex($workspaceId);

        DeferredIndexListener::disable();
        $this->softDeleteToggler->disable();

        $this->em->beginTransaction();

        $configuration = $this->em->getConnection()->getConfiguration();
        $logger = $configuration->getSQLLogger();
        $configuration->setSQLLogger();
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
            $this->deleteDependencies(AttributeClass::class, $workspaceId);

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
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        } finally {
            DeferredIndexListener::enable();
            $this->softDeleteToggler->enable();
            $configuration->setSQLLogger($logger);
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
