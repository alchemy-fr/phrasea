<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\Facet\FacetRegistry;
use Elastica\Aggregation\Missing;
use Elastica\Query;

final class FacetHandler
{
    public const MISSING_SUFFIX = '::missing';

    private FacetRegistry $facetRegistry;

    public function __construct(FacetRegistry $facetRegistry)
    {
        $this->facetRegistry = $facetRegistry;
    }

    public function addBuiltInFacets(Query $query): void
    {
        foreach ($this->facetRegistry->getAll() as $facet) {
            $facet->buildFacet($query);
            if ($facet->includesMissing()) {
                $missingAgg = new Missing($facet::getKey().self::MISSING_SUFFIX, $facet->getFieldName());
                $query->addAggregation($missingAgg);
            }
        }
    }

    public function normalizeBuckets(array $facets): array
    {
        $missing = [];
        $mergedFacets = [];
        foreach ($facets as $k => $f) {
            if (1 === preg_match('#'.preg_quote(self::MISSING_SUFFIX, '#').'$#', $k)) {
                $k = substr($k, 0, -strlen(self::MISSING_SUFFIX));

                $missing[$k] = $f['doc_count'];

                continue;
            }

            $facet = $this->facetRegistry->getFacet($k);

            if ($facet) {
                $f['buckets'] = array_values(array_filter(array_map(function (array $bucket) use ($facet): ?array {
                    return $facet->normalizeBucket($bucket);
                }, $f['buckets']), function ($value): bool {
                    return null !== $value;
                }));
            }

            $facetWidget = $f['meta']['widget'] ?? ESFacetInterface::TYPE_TEXT;
            if (ESFacetInterface::TYPE_DATE_RANGE === $facetWidget) {
                foreach ($f['buckets'] as &$bucket) {
                    $bucket['key'] = $bucket['key'] / 1000;
                }
            }

            $mergedFacets[$k] = $f;
        }

        foreach ($missing as $k => $count) {
            $mergedFacets[$k]['missing_count'] = $count;
        }

        return $mergedFacets;
    }
}
