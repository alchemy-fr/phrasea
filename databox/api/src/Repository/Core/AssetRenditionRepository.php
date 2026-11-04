<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class AssetRenditionRepository extends ServiceEntityRepository
{
    final public const string OPT_DEFINITION_IDS = 'definitionIds';
    final public const string OPT_USED_AS = 'usedAs';
    final public const string OPT_EXCLUDE_DEFINITIONS = 'excludeDefinitions';
    final public const string OPT_WITH_FILE = 'withFile';

    private readonly CacheInterface $renditionCache;

    public function __construct(
        ManagerRegistry $registry,
        TemporaryCacheFactory $cacheFactory,
    ) {
        parent::__construct($registry, AssetRendition::class);
        $this->renditionCache = $cacheFactory->createCache();
    }

    /**
     * @return AssetRendition[]
     */
    public function findAssetRenditions(string $assetId, array $options = []): array
    {
        return $this->createAssetRenditionsQueryBuilder($options)
            ->andWhere('t.asset = :asset')
            ->setParameter('asset', $assetId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Read-only variant of findAssetRenditions() backed by a per-request cache
     * (see prefetchAssetRenditions()). Meant for output/normalization code paths.
     *
     * @return AssetRendition[]
     */
    public function getCachedAssetRenditions(string $assetId, array $options = []): array
    {
        $excludedDefinitions = $options[self::OPT_EXCLUDE_DEFINITIONS] ?? [];
        unset($options[self::OPT_EXCLUDE_DEFINITIONS]);

        $renditions = $this->renditionCache->get(
            $this->getCacheKey($assetId, $options),
            fn (): array => $this->findAssetRenditions($assetId, $options)
        );

        if (empty($excludedDefinitions)) {
            return $renditions;
        }

        return array_values(array_filter(
            $renditions,
            fn (AssetRendition $rendition): bool => !in_array($rendition->getDefinition()?->getId(), $excludedDefinitions, true)
        ));
    }

    /**
     * Load the renditions of many assets in a single query and fill the
     * per-request cache used by getCachedAssetRenditions().
     *
     * @param string[] $assetIds
     */
    public function prefetchAssetRenditions(array $assetIds, array $options = []): void
    {
        unset($options[self::OPT_EXCLUDE_DEFINITIONS]);
        $assetIds = array_values(array_unique($assetIds));
        if (empty($assetIds)) {
            return;
        }

        $byAsset = array_fill_keys($assetIds, []);
        /** @var AssetRendition $rendition */
        foreach ($this->createAssetRenditionsQueryBuilder($options)
            ->andWhere('t.asset IN (:assets)')
            ->setParameter('assets', $assetIds)
            ->getQuery()
            ->getResult() as $rendition) {
            $byAsset[$rendition->getAsset()->getId()][] = $rendition;
        }

        foreach ($byAsset as $assetId => $renditions) {
            $this->renditionCache->get($this->getCacheKey($assetId, $options), fn (): array => $renditions);
        }
    }

    private function getCacheKey(string $assetId, array $options): string
    {
        ksort($options);

        return $assetId.'_'.md5(serialize($options));
    }

    private function createAssetRenditionsQueryBuilder(array $options): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t, f')
            ->addSelect('s')
            ->innerJoin('t.definition', 's')
            ->leftJoin('t.file', 'f')
            ->addOrderBy('s.priority', 'DESC');

        if ($options[self::OPT_DEFINITION_IDS] ?? false) {
            $queryBuilder
                ->andWhere('s.id IN (:def_ids)')
                ->setParameter('def_ids', $options[self::OPT_DEFINITION_IDS]);
        }
        if ($options[self::OPT_USED_AS] ?? false) {
            $queryBuilder
                ->andWhere(implode(' OR ', array_map(
                    fn (string $usedAs): string => 's.useAs'.ucfirst($usedAs).' = true',
                    RenditionDefinition::BUILT_IN_RENDITIONS
                )));
        }

        if ($options[self::OPT_EXCLUDE_DEFINITIONS] ?? false) {
            $queryBuilder
                ->andWhere('s.id NOT IN (:exclude_def_ids)')
                ->setParameter('exclude_def_ids', $options[self::OPT_EXCLUDE_DEFINITIONS]);
        }

        if (null !== ($options[self::OPT_WITH_FILE] ?? null)) {
            if ($options[self::OPT_WITH_FILE]) {
                $queryBuilder->andWhere('t.file IS NOT NULL');
            } else {
                $queryBuilder->andWhere('t.file IS NULL');
            }
        }

        return $queryBuilder;
    }

    public function findDynamicRenditionByName(string $assetId, string $name): ?AssetRendition
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.asset = :asset')
            ->andWhere('t.definition IS NULL')
            ->andWhere('t.name = :name')
            ->setParameter('asset', $assetId)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
