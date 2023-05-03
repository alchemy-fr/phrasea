<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Api\Filter\Group\GroupValue;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\ESFacetInterface;
use Doctrine\Common\Collections\Collection;
use Elastica\Aggregation;
use Elastica\Query;

abstract class AbstractFacet implements FacetInterface
{
    public function getType(): string
    {
        return TextAttributeType::NAME;
    }

    public function resolveGroupValue(string $name, $value): GroupValue
    {
        if ($value instanceof Collection) {
            $keys = [];
            $values = [];

            foreach ($value as $item) {
                $item = $this->resolveCollectionItem($item);
                $keys[] = $this->resolveKey($item);
                $values[] = $this->resolveItem($item) ?? $this->resolveLabel($item);
            }

            return new GroupValue($name, $this->getType(), implode(',', $keys), $values);
        }

        $item = $this->resolveCollectionItem($value);

        return new GroupValue($name, $this->getType(), $this->resolveKey($item), [$this->resolveItem($item) ?? $this->resolveLabel($item)]);
    }

    public function normalizeBucket(array $bucket): ?array
    {
        return $bucket;
    }

    protected function resolveCollectionItem($item)
    {
        return $item;
    }

    /**
     * Returns the object containing necessary properties for client display.
     *
     *
     * @return object|null
     */
    protected function resolveItem(mixed $value)
    {
        return null;
    }

    protected function resolveLabel($value): string
    {
        return $value;
    }

    protected function resolveKey($value): string
    {
        return $value;
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
