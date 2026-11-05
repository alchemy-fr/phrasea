<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Doctrine\Listener\AuditLogListener;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logs the attribute (and tag) changes of a batch that writes through DQL/SQL, which the
 * UnitOfWork does not see: the values of the assets are read before and after the batch,
 * one AssetAttributeChanged entry is written per changed asset.
 */
final readonly class AttributeChangeAuditor
{
    public const string TAGS = 'tags';

    private const int CHUNK = 500;

    public function __construct(
        private EntityManagerInterface $em,
        private AuditLogManager $auditLogManager,
    ) {
    }

    /**
     * @param string[]                                                                $assetIds
     * @param array<array{definitionId: ?string, action: ?string, assets: ?string[]}> $operations the actions of the batch
     * @param callable(): void                                                        $batch
     */
    public function audit(array $assetIds, array $operations, callable $batch): void
    {
        if (!AuditLogManager::$enabled || empty($assetIds)) {
            $batch();

            return;
        }

        $before = $this->snapshot($assetIds);

        $tracked = AuditLogListener::$trackAttributes;
        AuditLogListener::$trackAttributes = false;
        try {
            $batch();
        } finally {
            AuditLogListener::$trackAttributes = $tracked;
        }

        $after = $this->snapshot($assetIds);

        $changesByAsset = [];
        $definitionIds = [];
        foreach ($assetIds as $assetId) {
            $old = $before[$assetId] ?? [];
            $new = $after[$assetId] ?? [];
            foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
                $oldValues = $old[$key]['values'] ?? [];
                $newValues = $new[$key]['values'] ?? [];
                sort($oldValues);
                sort($newValues);
                if ($oldValues === $newValues) {
                    continue;
                }

                $definitionId = $old[$key]['definitionId'] ?? $new[$key]['definitionId'];
                $definitionIds[$definitionId] = true;
                $op = empty($newValues) ? BatchAttributeManager::ACTION_DELETE : self::getOperation($operations, $assetId, $definitionId);

                $changesByAsset[$assetId][] = [
                    'definitionId' => $definitionId,
                    'definitionName' => null,
                    'locale' => $old[$key]['locale'] ?? $new[$key]['locale'],
                    'op' => $op,
                    'old' => $old[$key]['values'] ?? [],
                    'new' => $new[$key]['values'] ?? [],
                ];
            }
        }

        if (empty($changesByAsset)) {
            return;
        }

        $names = $this->getDefinitionNames(array_keys($definitionIds));
        $assets = $this->em->getRepository(Asset::class)->findByIds(array_keys($changesByAsset));
        foreach ($assets as $asset) {
            $changes = array_map(static function (array $change) use ($names): array {
                $change['definitionName'] = self::TAGS === $change['definitionId'] ? 'Tags' : ($names[$change['definitionId']] ?? null);

                return $change;
            }, $changesByAsset[$asset->getId()]);

            $this->auditLogManager->log(AuditLogActionEnum::AssetAttributeChanged, $asset, [
                'changes' => $changes,
            ]);
        }
        $this->em->flush();
    }

    /**
     * The action that changed the values of a definition of an asset ("set" when several did).
     */
    private static function getOperation(array $operations, string $assetId, string $definitionId): string
    {
        $actions = [];
        foreach ($operations as $operation) {
            if ((null === $operation['definitionId'] || $operation['definitionId'] === $definitionId)
                && (null === $operation['assets'] || in_array($assetId, $operation['assets'], true))) {
                $actions[$operation['action'] ?? BatchAttributeManager::ACTION_SET] = true;
            }
        }

        return 1 === count($actions) ? array_key_first($actions) : BatchAttributeManager::ACTION_SET;
    }

    /**
     * @return array<string, array<string, array{definitionId: string, locale: ?string, values: string[]}>> asset ID => definition|locale => values
     */
    private function snapshot(array $assetIds): array
    {
        $snapshot = [];
        foreach (array_chunk(array_values($assetIds), self::CHUNK) as $chunk) {
            $rows = $this->em->createQueryBuilder()
                ->select('IDENTITY(a.asset) AS assetId', 'IDENTITY(a.definition) AS definitionId', 'a.locale AS locale', 'a.value AS value')
                ->from(Attribute::class, 'a')
                ->andWhere('a.asset IN (:ids)')
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->getScalarResult();

            foreach ($rows as $row) {
                $key = $row['definitionId'].'|'.($row['locale'] ?? '');
                $snapshot[$row['assetId']][$key]['definitionId'] = (string) $row['definitionId'];
                $snapshot[$row['assetId']][$key]['locale'] = $row['locale'];
                $snapshot[$row['assetId']][$key]['values'][] = $row['value'];
            }

            foreach ($this->getTags($chunk) as $row) {
                $key = self::TAGS.'|';
                $snapshot[$row['asset_id']][$key]['definitionId'] = self::TAGS;
                $snapshot[$row['asset_id']][$key]['locale'] = null;
                $snapshot[$row['asset_id']][$key]['values'][] = $row['tag_id'];
            }
        }

        return $snapshot;
    }

    /**
     * @return array<array{asset_id: string, tag_id: string}>
     */
    private function getTags(array $assetIds): array
    {
        $joinTable = $this->em->getClassMetadata(Asset::class)->getAssociationMapping('tags')['joinTable'];

        return $this->em->getConnection()->fetchAllAssociative(sprintf(
            'SELECT %2$s AS asset_id, %3$s AS tag_id FROM %1$s WHERE %2$s IN (:ids)',
            $joinTable['name'],
            $joinTable['joinColumns'][0]['name'],
            $joinTable['inverseJoinColumns'][0]['name'],
        ), [
            'ids' => $assetIds,
        ], [
            'ids' => ArrayParameterType::STRING,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getDefinitionNames(array $definitionIds): array
    {
        $definitionIds = array_values(array_filter($definitionIds, static fn (string $id): bool => self::TAGS !== $id));
        if (empty($definitionIds)) {
            return [];
        }

        $names = [];
        foreach ($this->em->getRepository(AttributeDefinition::class)->findBy(['id' => $definitionIds]) as $definition) {
            $names[$definition->getId()] = $definition->getName();
        }

        return $names;
    }
}
