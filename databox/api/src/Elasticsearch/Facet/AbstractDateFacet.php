<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\DateAttributeType;
use App\Elasticsearch\ESFacetInterface;
use Elastica\Query;
use Elastica\Aggregation;

abstract class AbstractDateFacet extends AbstractFacet
{
    public function normalizeBucket(array $bucket): ?array
    {
        return $bucket;
    }

    public function buildFacet(Query $query): void
    {
        $agg = new Aggregation\AutoDateHistogram(
            static::getKey(),
            $this->getFieldName()
        );
        $agg->setBuckets($this->getAggregationSize());
        $agg->setMinimumInterval('minute');
        $agg->setMeta($this->getAggregationMeta());
        $query->addAggregation($agg);
    }

    protected function getFacetWidget(): string
    {
        return ESFacetInterface::TYPE_DATE_RANGE;
    }

    public function getType(): string
    {
        return DateAttributeType::getName();
    }
}
