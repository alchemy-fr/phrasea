<?php

namespace App\Service\Collection;

use App\Entity\Core\Collection;
use App\Repository\Core\CollectionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
        ];

        $this->connection->executeQuery('DELETE FROM collection_access
WHERE collection_id = :id AND user_id NOT IN (
    SELECT owner_id::uuid FROM collection WHERE id = :id AND owner_id IS NOT NULL
    UNION
    SELECT user_id::uuid FROM access_control_entry WHERE object_type = :tcoll AND object_id = :id AND (mask & 1) = 1
)
', $params);

        $this->connection->executeQuery('INSERT INTO collection_access
            (workspace_id, collection_id, user_id, path, privacy)
            (SELECT c.workspace_id, c.id, c.owner_id::uuid, :path, c.privacy
            FROM collection c
            WHERE c.id = :id AND (c.privacy > 0 OR c.owner_id IS NOT NULL))
            ON CONFLICT (collection_id, user_id) DO UPDATE SET path = EXCLUDED.path, privacy = EXCLUDED.privacy',
            $params
        );

        $this->connection->executeQuery('INSERT INTO collection_access
            (workspace_id, collection_id, user_id, path, privacy)
            (SELECT c.workspace_id, c.id, ace.user_id::uuid, :path, c.privacy
            FROM collection c
            INNER JOIN access_control_entry ace ON ace.object_type = :tcoll AND ace.object_id = c.id AND (ace.mask & 1) = 1
            WHERE c.id = :id)
            ON CONFLICT (collection_id, user_id) DO UPDATE SET path = EXCLUDED.path, privacy = EXCLUDED.privacy',
            $params);

        $this->connection->executeQuery('DELETE FROM collection_access ca
WHERE collection_id = :id AND EXISTS (
    SELECT 1 FROM collection_access a
    WHERE a.path @> ca.path
    AND a.user_id = ca.user_id
    AND a.collection_id <> ca.collection_id
)
', $params);

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
        $baseQuery = $this->collectionRepository->createQueryBuilder('t')
            ->andWhere('t.storyAsset IS NULL');

        $progressBar = null;
        if (null !== $output) {
            $total = (clone $baseQuery)
                ->select('COUNT(t.id)')
                ->getQuery()
                ->getSingleScalarResult();
            $progressBar = new ProgressBar($output, $total);
        }

        $iterator = (clone $baseQuery)
            ->select('t')
            ->andWhere('t.parent IS NULL')
            ->getQuery()
            ->toIterable();

        foreach ($iterator as $collection) {
            $this->computeCollection($collection, $progressBar);
        }

        $progressBar->finish();
    }
}
