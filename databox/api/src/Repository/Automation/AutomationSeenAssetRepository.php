<?php

declare(strict_types=1);

namespace App\Repository\Automation;

use App\Entity\Automation\AutomationSeenAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The cursor of the automations, read and written in bulk through DBAL.
 *
 * @extends ServiceEntityRepository<AutomationSeenAsset>
 */
class AutomationSeenAssetRepository extends ServiceEntityRepository
{
    private const string TABLE = 'automation_seen_asset';
    private const int CHUNK_SIZE = 500;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutomationSeenAsset::class);
    }

    /**
     * Marks the assets as seen by the automation, keeping the first time they were.
     *
     * @param string[] $assetIds
     */
    public function markSeen(string $automationId, array $assetIds, ?string $runId = null): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $now = new \DateTimeImmutable();

        foreach (array_chunk(array_values(array_unique($assetIds)), self::CHUNK_SIZE) as $chunk) {
            $rows = [];
            $params = [];
            $types = [];
            foreach ($chunk as $assetId) {
                $rows[] = '(?, ?, ?, ?)';
                array_push($params, $automationId, $assetId, $now, $runId);
                array_push($types, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE, null === $runId ? ParameterType::NULL : ParameterType::STRING);
            }

            $connection->executeStatement(sprintf(
                'INSERT INTO %s (automation_id, asset_id, first_seen_at, run_id) VALUES %s ON CONFLICT DO NOTHING',
                self::TABLE,
                implode(', ', $rows),
            ), $params, $types);
        }
    }

    /**
     * @param string[] $assetIds
     *
     * @return string[] the given assets not seen yet by the automation
     */
    public function filterUnseen(string $automationId, array $assetIds): array
    {
        if (empty($assetIds)) {
            return [];
        }

        $seen = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            sprintf('SELECT asset_id FROM %s WHERE automation_id = :a AND asset_id IN (:ids)', self::TABLE),
            [
                'a' => $automationId,
                'ids' => array_values($assetIds),
            ],
            [
                'a' => ParameterType::STRING,
                'ids' => ArrayParameterType::STRING,
            ],
        );
        $seenIndex = array_flip(array_map(strval(...), $seen));

        return array_values(array_filter($assetIds, fn (string $id): bool => !isset($seenIndex[$id])));
    }

    public function countSeen(string $automationId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE automation_id = :a', self::TABLE),
            ['a' => $automationId],
        );
    }

    /**
     * Empties the cursor: every result becomes new again.
     */
    public function reset(string $automationId): int
    {
        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            sprintf('DELETE FROM %s WHERE automation_id = :a', self::TABLE),
            ['a' => $automationId],
        );
    }
}
