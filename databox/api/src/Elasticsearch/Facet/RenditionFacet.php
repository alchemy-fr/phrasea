<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use Elastica\Aggregation;
use LogicException;

final class RenditionFacet extends AbstractFacet
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
            'label' => $this->resolveValue($this->em->find(RenditionDefinition::class, $bucket['key'])),
        ];

        return $bucket;
    }

    /**
     * @param RenditionDefinition $value
     *
     * @return string
     */
    public function resolveValue($value): string
    {
        return $value->getName();
    }

    public function getFieldName(): string
    {
        return 'renditions';
    }

    public static function getKey(): string
    {
        return 'r';
    }

    public function isValueAccessibleFromDatabase(): bool
    {
        return false;
    }

    public function getValueFromAsset(Asset $asset)
    {
        throw new LogicException('Should never be called');
    }

    protected function getAggregationTitle(): string
    {
        return 'Renditions';
    }

    public function isSortable(): bool
    {
        return false;
    }
}
