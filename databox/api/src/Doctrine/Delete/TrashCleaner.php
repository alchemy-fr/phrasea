<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use App\Entity\Core\Workspace;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\CollectionRepository;
use App\Repository\Core\WorkspaceRepository;
use Psr\Log\LoggerInterface;

final readonly class TrashCleaner
{
    public function __construct(
        private WorkspaceRepository $workspaceRepository,
        private CollectionRepository $collectionRepository,
        private AssetRepository $assetRepository,
        private CollectionDelete $collectionDelete,
        private AssetDelete $assetDelete,
        private LoggerInterface $logger,
    ) {
    }

    public function emptyTrash(): void
    {
        $workspaces = $this->workspaceRepository
            ->createQueryBuilder('w')
            ->select('w')
            ->getQuery()
            ->toIterable();

        /** @var Workspace $workspace */
        foreach ($workspaces as $workspace) {
            $this->cleanWorkspace($workspace->getId(), $workspace->getTrashRetentionDelay());
        }
    }

    private function cleanWorkspace(string $workspaceId, int $retentionDelay): void
    {
        $this->logger->info(sprintf('Emptying trash for workspace "%s"', $workspaceId));

        $collections = $this->collectionRepository->createQueryBuilder('t')
            ->select('t.id')
            ->andWhere('t.workspace = :workspaceId')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->andWhere('t.deletedAt <= :deletionDate')
            ->setParameter('workspaceId', $workspaceId)
            ->setParameter('deletionDate', new \DateTimeImmutable(sprintf('-%d days', $retentionDelay)))
            ->getQuery()
            ->toIterable();

        foreach ($collections as $collection) {
            $this->collectionDelete->deleteCollection((string) $collection['id']);
        }

        $assets = $this->assetRepository->createQueryBuilder('t')
            ->select('t.id')
            ->andWhere('t.workspace = :workspaceId')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->andWhere('t.deletedAt <= :deletionDate')
            ->setParameter('workspaceId', $workspaceId)
            ->setParameter('deletionDate', new \DateTimeImmutable(sprintf('-%d days', $retentionDelay)))
            ->getQuery()
            ->toIterable();

        $batchSize = 100;
        $stack = [];
        $i = 0;
        foreach ($assets as $asset) {
            $stack[] = $asset['id'];
            if ($i++ >= $batchSize) {
                $this->assetDelete->deleteAssets($stack);
                $stack = [];
                $i = 0;
            }
        }
        if (!empty($stack)) {
            $this->assetDelete->deleteAssets($stack);
        }
    }
}
