<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

/**
 * For built-in attributes that cannot be sorted by naming an Elasticsearch field,
 * because the sort depends on the current filters.
 */
interface CustomSortBuiltInAttributeInterface extends BuiltInAttributeInterface
{
    /**
     * @param string $way     ASC or DESC
     * @param array  $options the search options, to resolve the sort against the active filters
     *
     * @return array a raw Elasticsearch sort clause
     */
    public function createSortClause(string $way, array $options): array;
}
