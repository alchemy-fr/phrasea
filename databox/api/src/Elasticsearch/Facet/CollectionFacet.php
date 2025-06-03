<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Api\Traits\UserLocaleTrait;
use App\Attribute\Type\CollectionPathAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class CollectionFacet extends AbstractFacet
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

    public function normalizeBucket(array $bucket): ?array
    {
        $label = $this->normalizeCollectionPath($bucket['key']);
        if (null === $label) {
            return null;
        }

        $parts = explode('/', $bucket['key']);

        $bucket['key'] = [
            'value' => $parts[array_key_last($parts)],
            'label' => $label,
        ];

        return $bucket;
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

    protected function getAggregationTitle(): string
    {
        return 'Collections';
    }

    private function normalizeCollectionPath(string $path): ?string
    {
        $ids = explode('/', $path);
        $id = $ids[array_key_last($ids)];

        $collection = $this->em->find(Collection::class, $id);
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

        return implode(' / ', array_reverse($levels));
    }

    public function normalizeValueForSearch(mixed $value): mixed
    {
        return $this->em->find(Collection::class, $value)?->getAbsolutePath();
    }
}
