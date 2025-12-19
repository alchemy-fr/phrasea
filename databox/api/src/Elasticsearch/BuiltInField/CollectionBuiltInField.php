<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\Type\CollectionPathAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class CollectionBuiltInField extends AbstractBuiltInField
{
    use UserLocaleTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function getType(): string
    {
        return CollectionPathAttributeType::getName();
    }

    private static function extractIdFromPath(string $path): string
    {
        $parts = explode('/', $path);

        return $parts[array_key_last($parts)];
    }

    public function normalizeBuckets(array $buckets): array
    {
        $ids = array_map(function (array $bucket): string {
            return self::extractIdFromPath($bucket['key']);
        }, $buckets);

        $collections = DoctrineUtil::getIndexFromIds($this->em->getRepository(Collection::class), $ids);

        return array_map(function (array $bucket) use ($collections): ?array {
            $id = self::extractIdFromPath($bucket['key']);

            $collection = $collections[$id] ?? null;
            if (null === $collection) {
                return null;
            }
            $preferredLocales = $this->getPreferredLocales($collection->getWorkspace());
            $levels = [];
            $pColl = $collection;
            while ($pColl) {
                if (!$this->security->isGranted(AbstractVoter::READ, $pColl)) {
                    break;
                }

                $levels[] = $pColl->getTranslatedField('title', $preferredLocales, $pColl->getTitle()) ?? $pColl->getId();
                $pColl = $pColl->getParent();
            }

            if (empty($levels)) {
                return null;
            }

            $bucket['key'] = [
                'value' => $collection->getId(),
                'label' => implode(' / ', array_reverse($levels)),
            ];

            return $bucket;
        }, $buckets);
    }

    /**
     * @param CollectionAsset $item
     */
    protected function resolveCollectionItem($item): Collection
    {
        return $item->getCollection();
    }

    /**
     * @param Collection $value
     */
    public function resolveLabel($value): string
    {
        return $value->getTitle();
    }

    /**
     * @param Collection $value
     */
    protected function resolveKey($value): string
    {
        return $value->getId();
    }

    public function getFieldName(): string
    {
        return 'collectionPaths';
    }

    public static function getKey(): string
    {
        return '@collection';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getCollections();
    }

    protected function resolveItem($value)
    {
        return $value;
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'collections';
    }

    public function normalizeValueForSearch(mixed $value): mixed
    {
        return $this->em->find(Collection::class, $value)?->getAbsolutePath();
    }
}
