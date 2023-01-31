<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\Facet\FacetRegistry;
use Elastica\Query;

class FacetHandler
{
    private FacetRegistry $facetRegistry;

    public function __construct(FacetRegistry $facetRegistry)
    {
        $this->facetRegistry = $facetRegistry;
    }

    public function buildFacets(Query $query): void
    {
        foreach ($this->facetRegistry->getAll() as $facet) {
            $facet->buildFacet($query);
        }
    }

    public function normalizeBuckets(array $facets): array
    {
        foreach ($facets as $k => &$f) {
            $facet = $this->facetRegistry->getFacet($k);

            if ($facet) {
                $f['buckets'] = array_values(array_filter(array_map(function (array $bucket) use ($facet): ?array {
                    return $facet->normalizeBucket($bucket);
                }, $f['buckets'])));
            }

            $type = $f['meta']['type'] ?? FacetInterface::TYPE_STRING;
            if (FacetInterface::TYPE_DATE_RANGE === $type) {
                foreach ($f['buckets'] as &$bucket) {
                    $bucket['key'] = $bucket['key'] / 1000;
                }
            }
        }

        return $facets;
    }
}
