<?php

declare(strict_types=1);

namespace App\Service\Collection;

use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
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
            'everyone' => WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS,
        ];

        // A NULL user stands for everyone (ACE granted to everyone). Such an ACE only lists
        // the collection to authenticated users (CollectionVoter requires a user for ACL),
        // hence the PUBLIC_FOR_USERS floor.
        $queries = [
            'SELECT owner_id::uuid AS user_id, privacy
                FROM collection
                WHERE id = :id AND (privacy > 0 OR owner_id IS NOT NULL)',
            'SELECT user_id::uuid, CASE WHEN user_id IS NULL THEN GREATEST(CAST(:privacy AS SMALLINT), CAST(:everyone AS SMALLINT)) ELSE CAST(:privacy AS SMALLINT) END
                FROM access_control_entry
                WHERE object_type = :tcoll AND object_id = :id AND (mask & 1) = 1',
        ];
        if ($collection->isRoot()) {
            $queries[] = 'SELECT user_id::uuid, CASE WHEN user_id IS NULL THEN GREATEST(CAST(:privacy AS SMALLINT), CAST(:everyone AS SMALLINT)) ELSE CAST(:privacy AS SMALLINT) END
                FROM access_control_entry
                WHERE object_type = :tws AND object_id = :wsid AND (mask & 128) = 128';
            $queries[] = 'SELECT owner_id::uuid, CAST(:privacy AS SMALLINT)
                FROM workspace
                WHERE id = :wsid AND owner_id IS NOT NULL';
        }
        $queries = implode(' UNION ', $queries);

        // The rows are replaced as a whole: a NOT IN purge never matches a NULL user, and
        // ON CONFLICT never sees two NULL users as the same. Grouping by user keeps a single
        // row per user, the NULL one included, with the widest privacy.
        $this->connection->transactional(function (Connection $connection) use ($params, $queries): void {
            // Concurrent recomputations of the same collection (Messenger workers) run one after the other
            $connection->executeQuery('SELECT pg_advisory_xact_lock(hashtext(CAST(:id AS TEXT)))', $params);

            $connection->executeStatement('DELETE FROM collection_access WHERE collection_id = :id', $params);

            $connection->executeStatement('INSERT INTO collection_access
    (workspace_id, collection_id, user_id, path, privacy)
    SELECT CAST(:wsid AS UUID), CAST(:id AS UUID), g.user_id, CAST(:path AS LTREE), MAX(g.privacy)
    FROM ('.$queries.') g (user_id, privacy)
    GROUP BY g.user_id
    ON CONFLICT DO NOTHING',
                $params
            );

            $connection->executeStatement('DELETE FROM collection_access ca
WHERE collection_id = :id AND EXISTS (
    SELECT 1 FROM collection_access a
    WHERE a.path @> ca.path
    AND a.user_id = ca.user_id
    AND a.collection_id <> ca.collection_id
)', $params);
        });

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
