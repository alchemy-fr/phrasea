<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

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
        $agg->setMeta([
            'title' => $this->getAggregationTitle(),
            'type' => 'date_range',
            'sortable' => $this->isSortable(),
        ]);
        $query->addAggregation($agg);
    }
}
