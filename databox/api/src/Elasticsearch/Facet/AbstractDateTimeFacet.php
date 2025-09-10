<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\DateTimeAttributeType;
use App\Elasticsearch\ESFacetInterface;
use Elastica\Aggregation;
use Elastica\Query;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractDateTimeFacet extends AbstractFacet
{
    protected function resolveLabel($value): string
    {
        return $this->resolveKey($value);
    }

    protected function resolveKey($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return $value;
    }

    public function buildFacet(Query $query, TranslatorInterface $translator): void
    {
        $agg = new Aggregation\AutoDateHistogram(
            static::getKey(),
            $this->getFieldName()
        );
        $agg->setBuckets($this->getAggregationSize());
        $agg->setMinimumInterval($this->getAggregationMinimumInterval());
        $agg->setMeta($this->getAggregationMeta($translator));
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
