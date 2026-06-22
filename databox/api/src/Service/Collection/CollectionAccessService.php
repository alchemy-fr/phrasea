<?php

declare(strict_types=1);

namespace App\Service\Collection;

use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Repository\Core\CollectionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\QueryBuilder;
use MartinGeorgiev\Doctrine\DBAL\Types\ValueObject\Ltree;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class CollectionAccessService
{
    public function __construct(
        private Connection $connection,
        private CollectionRepository $collectionRepository,
    ) {
    }

    public function computeCollection(Collection $collection, ?ProgressBar $progressBar = null): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            // Don't compute on SQLite database for testing
            // Tests should fill this table
            return;
        }

        $absolutePath = str_replace('-', 'Z', $collection->getAbsolutePath());
        $params = [
            'id' => $collection->getId(),
            'path' => new Ltree(explode('/', substr($absolutePath, 1)))->__toString(),
            'tcoll' => Collection::OBJECT_TYPE,
            'tws' => Workspace::OBJECT_TYPE,
            'wsid' => $collection->getWorkspaceId(),
            'privacy' => $collection->getPrivacy(),
        ];

        $this->connection->executeQuery('DELETE FROM collection_access
WHERE collection_id = :id AND user_id NOT IN (
    SELECT owner_id::uuid FROM collection WHERE id = :id AND owner_id IS NOT NULL
    UNION
    SELECT user_id::uuid FROM access_control_entry WHERE object_type = :tcoll AND object_id = :id AND (mask & 1) = 1
    UNION
    SELECT user_id::uuid FROM access_control_entry WHERE object_type = :tws AND object_id = :wsid AND (mask & 128) = 128
)', $params);

        $queries = [
            'SELECT workspace_id, id, owner_id::uuid, :path::ltree, privacy
                FROM collection
                WHERE id = :id AND (privacy > 0 OR owner_id IS NOT NULL)',
            'SELECT :wsid, :id, user_id::uuid, :path, :privacy
                FROM access_control_entry
                WHERE object_type = :tcoll AND object_id = :id AND (mask & 1) = 1',
        ];
        if ($collection->isRoot()) {
            $queries[] = 'SELECT :wsid, :id, user_id::uuid, :path, :privacy
                FROM access_control_entry
                WHERE object_type = :tws AND object_id = :wsid AND (mask & 128) = 128';
            $queries[] = 'SELECT :wsid, :id, owner_id::uuid, :path, :privacy
                FROM workspace
                WHERE id = :wsid AND owner_id IS NOT NULL';
        }
        $queries = implode(' UNION ', $queries);

        $this->connection->executeQuery('INSERT INTO collection_access
    (workspace_id, collection_id, user_id, path, privacy)
    ('.$queries.')
    ON CONFLICT (collection_id, user_id) DO UPDATE SET path = EXCLUDED.path, privacy = EXCLUDED.privacy',
            $params
        );

        $this->connection->executeQuery('DELETE FROM collection_access ca
WHERE collection_id = :id AND EXISTS (
    SELECT 1 FROM collection_access a
    WHERE a.path @> ca.path
    AND a.user_id = ca.user_id
    AND a.collection_id <> ca.collection_id
)', $params);

        $children = $this->collectionRepository->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.storyAsset IS NULL')
            ->andWhere('t.parent = :parent')
            ->setParameter('parent', $collection->getId())
            ->getQuery()
            ->toIterable();

        $progressBar?->advance();

        foreach ($children as $child) {
            $this->computeCollection($child, $progressBar);
        }
    }

    public function recomputeAll(?OutputInterface $output = null): void
    {
        $this->compute($this->collectionRepository->createQueryBuilder('t'), $output);
    }

    public function recomputeWorkspace(string $workspaceId, ?OutputInterface $output = null): void
    {
        $this->compute($this->collectionRepository->createQueryBuilder('t')
            ->andWhere('t.workspace = :ws')
            ->setParameter('ws', $workspaceId), $output);
    }

    private function compute(QueryBuilder $queryBuilder, ?OutputInterface $output = null): void
    {
        $queryBuilder->andWhere('t.storyAsset IS NULL');

        $progressBar = null;
        if (null !== $output) {
            $total = (clone $queryBuilder)
                ->select('COUNT(t.id)')
                ->getQuery()
                ->getSingleScalarResult();
            $progressBar = new ProgressBar($output, $total);
        }

        $iterator = (clone $queryBuilder)
            ->select('t')
            ->andWhere('t.parent IS NULL')
            ->getQuery()
            ->toIterable();

        foreach ($iterator as $collection) {
            $this->computeCollection($collection, $progressBar);
        }

        $progressBar?->finish();
    }
}
