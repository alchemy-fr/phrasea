<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\ESFacetInterface;
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
        $agg->setMeta($this->getAggregationMeta());
        $query->addAggregation($agg);
    }

    protected function getAggregationMeta(): array
    {
        $meta = [
            'title' => $this->getAggregationTitle(),
            'sortable' => $this->isSortable(),
        ];
        if (TextAttributeType::NAME !== $this->getType()) {
            $meta['type'] = $this->getType();
        }
        if (ESFacetInterface::TYPE_TEXT !== $this->getFacetWidget()) {
            $meta['widget'] = $this->getFacetWidget();
        }

        return $meta;
    }

    protected function getAggregationSize(): int
    {
        return 20;
    }

    protected function getFacetWidget(): string
    {
        return ESFacetInterface::TYPE_TEXT;
    }

    public function includesMissing(): bool
    {
        return true;
    }

    abstract protected function getAggregationTitle(): string;
}
