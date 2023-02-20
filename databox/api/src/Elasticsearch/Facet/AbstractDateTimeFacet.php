<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\DateTimeAttributeType;
use App\Elasticsearch\ESFacetInterface;
use Elastica\Query;
use Elastica\Aggregation;

abstract class AbstractDateTimeFacet extends AbstractFacet
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
        $agg->setMinimumInterval($this->getAggregationMinimumInterval());
        $agg->setMeta($this->getAggregationMeta());
        $query->addAggregation($agg);
    }

    protected function getAggregationMinimumInterval(): string
    {
        return 'minute';
    }

    protected function getFacetWidget(): string
    {
        return ESFacetInterface::TYPE_DATE_RANGE;
    }

    public function getType(): string
    {
        return DateTimeAttributeType::getName();
    }
}
