<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\Tag;
use Doctrine\ORM\EntityManagerInterface;

final class TagFacet extends AbstractFacet
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function normalizeBucket(array $bucket): ?array
    {
        $bucket['key'] = [
            'value' => $bucket['key'],
            'label' => $this->em->find(Tag::class, $bucket['key'])->getName(),
        ];

        return $bucket;
    }

    /**
     * @param Tag $value
     *
     * @return string
     */
    public function resolveValue($value): string
    {
        return $value->getName();
    }

    public function getFieldName(): string
    {
        return 'tags';
    }

    public static function getKey(): string
    {
        return 't';
    }

    public function getValueFromAsset(Asset $asset)
    {
        return $asset->getCollections();
    }

    protected function getAggregationTitle(): string
    {
        return 'Tags';
    }
}
