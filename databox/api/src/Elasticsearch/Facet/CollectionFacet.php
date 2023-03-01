<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

final class CollectionFacet extends AbstractFacet
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function normalizeBucket(array $bucket): ?array
    {
        $label = $this->normalizeCollectionPath($bucket['key']);
        if (null === $label) {
            return null;
        }

        $bucket['key'] = [
            'value' => $bucket['key'],
            'label' => $label,
        ];

        return $bucket;
    }

    /**
     * @param CollectionAsset $item
     *
     * @return Collection
     */
    protected function resolveCollectionItem($item)
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
        return 'c';
    }

    public function getValueFromAsset(Asset $asset)
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
        array_shift($ids);

        /** @var Collection[] $collections */
        $collections = array_filter(array_map(function (string $id): ?Collection {
            return $this->em->find(Collection::class, $id);
        }, $ids));

        if (empty($collections) || count($collections) < count($ids)) {
            return null;
        }

        if (!$this->security->isGranted(CollectionVoter::READ, $collections[count($collections) - 1])) {
            return null;
        }

        return implode(' / ', array_map(function (Collection $c): ?string {
            return $c->getTitle() ?? $c->getId();
        }, $collections));
    }
}
