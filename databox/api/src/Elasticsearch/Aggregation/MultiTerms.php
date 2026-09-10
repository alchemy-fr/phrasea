<?php

declare(strict_types=1);

namespace App\Elasticsearch\Aggregation;

use Elastica\Aggregation\AbstractAggregation;

/**
 * "multi_terms" bucket aggregation (Elasticsearch >= 7.12), missing from Elastica.
 *
 * Buckets are keyed by the combination of the given fields and ordered by document count.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.17/search-aggregations-bucket-multi-terms-aggregation.html
 */
final class MultiTerms extends AbstractAggregation
{
    /**
     * @param string[] $fields
     */
    public function __construct(string $name, array $fields)
    {
        parent::__construct($name);
        $this->setParam('terms', array_map(fn (string $field): array => ['field' => $field], $fields));
    }

    public function setSize(int $size): self
    {
        return $this->setParam('size', $size);
    }
}
