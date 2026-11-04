<?php

declare(strict_types=1);

namespace App\Elasticsearch\Filter;

use Elastica\Query;

/**
 * The Elasticsearch query under construction, handed to the parameter filters.
 *
 * Filters narrow the result with `$query->bool->addFilter()`, score it with
 * `$query->bool->addMust()`, and order it with {@see addSort()}. The search class
 * falls back to its own default sort when no filter set one.
 */
final class SearchQuery
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $sort = [];

    public function __construct(
        public readonly Query\BoolQuery $bool,
    ) {
    }

    /**
     * @param array<string, mixed> $clause an Elasticsearch sort clause, e.g. `['name.raw' => 'asc']`
     */
    public function addSort(array $clause): void
    {
        $this->sort[] = $clause;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    public function hasSort(): bool
    {
        return [] !== $this->sort;
    }
}
