<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\TextAttributeType;
use Elastica\Query;
use Elastica\Aggregation;

abstract class AbstractFacet implements FacetInterface
{
    public function getType(): string
    {
        return TextAttributeType::NAME;
    }

    public function resolveValue($value)
    {
        return $value;
    }

    public function isValueAccessibleFromDatabase(): bool
    {
        return true;
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function buildFacet(Query $query): void
    {
        $agg = new Aggregation\Terms(static::getKey());
        $agg->setField($this->getFieldName());
        $agg->setSize($this->getAggregationSize());
        $agg->setMeta([
            'title' => $this->getAggregationTitle(),
            'sortable' => $this->isSortable(),
        ]);
        $query->addAggregation($agg);
    }

    protected function getAggregationSize(): int
    {
        return 20;
    }

    abstract protected function getAggregationTitle(): string;
}
