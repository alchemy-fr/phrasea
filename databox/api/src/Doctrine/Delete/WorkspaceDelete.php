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
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Integration\WorkspaceSecret;
use App\Entity\Template\AssetDataTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class WorkspaceDelete
{
    public function __construct(
        private EntityManagerInterface $em,
        private CollectionDelete       $collectionDelete,
        private IndexCleaner           $indexCleaner,
        private SoftDeleteToggler      $softDeleteToggler,
        private LoggerInterface        $logger,
    ) {
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

        $this->logger->debug('Cleaning index.');
        $this->indexCleaner->removeWorkspaceFromIndex($workspaceId);

        DeferredIndexListener::disable();
        $this->softDeleteToggler->disable();

        $this->em->beginTransaction();

        $configuration = $this->em->getConnection()->getConfiguration();
        $sqlLogger = $configuration->getSQLLogger();
        $configuration->setSQLLogger();
        try {
            $collections = $this->em->getRepository(Collection::class)
                ->createQueryBuilder('t')
                ->select('t.id, t.title')
                ->andWhere('t.parent IS NULL')
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspaceId)
                ->getQuery()
                ->toIterable();

            foreach ($collections as $c) {
                $this->logger->debug(sprintf('Deleting collection "%s" (%s).', $c['title'] ?: '', $c['id']));
                $this->collectionDelete->deleteCollection((string) $c['id'], true);
            }

            $this->deleteDependencies(Tag::class, $workspaceId);
            $this->deleteDependencies(RenditionDefinition::class, $workspaceId);
            $this->deleteDependencies(RenditionClass::class, $workspaceId);
            $this->deleteDependencies(AttributeDefinition::class, $workspaceId);
            $this->deleteDependencies(AttributeClass::class, $workspaceId);
            $this->deleteDependencies(AssetDataTemplate::class, $workspaceId);
            $this->deleteDependencies(WorkspaceIntegration::class, $workspaceId);
            $this->deleteDependencies(WorkspaceSecret::class, $workspaceId);

            $nFiles = $this->em->getRepository(File::class)
                ->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspaceId)
                ->getQuery()
                ->getSingleScalarResult();

            $files = $this->em->getRepository(File::class)
                ->createQueryBuilder('t')
                ->select('t.id')
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $workspaceId)
                ->getQuery()
                ->toIterable();

            $this->logger->debug(sprintf('Deleting %d Files', $nFiles));
            foreach ($files as $file) {
                $f = $this->em->find(File::class, $file['id']);
                $this->em->remove($f);
                $this->em->flush();
            }

            $workspace = $this->em->find(Workspace::class, $workspaceId);
            $this->logger->debug('Deleting workspace.');
            $this->em->remove($workspace);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        } finally {
            DeferredIndexListener::enable();
            $this->softDeleteToggler->enable();
            $configuration->setSQLLogger($sqlLogger);
        }
    }

    private function deleteDependencies(string $entityClass, string $workspaceId): void
    {
        $p = explode('\\', $entityClass);
        $this->logger->debug(sprintf('Deleting %s(s)', array_pop($p)));

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
