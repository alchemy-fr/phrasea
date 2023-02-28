<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Facet\FacetRegistry;
use Elastica\Aggregation\Missing;
use Elastica\Query;

final class FacetHandler
{
    public const MISSING_SUFFIX = '::missing';

    private FacetRegistry $facetRegistry;
    private AttributeTypeRegistry $attributeTypeRegistry;

    public function __construct(
        FacetRegistry $facetRegistry,
        AttributeTypeRegistry $attributeTypeRegistry
    ) {
        $this->facetRegistry = $facetRegistry;
        $this->attributeTypeRegistry = $attributeTypeRegistry;
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
                try {
                    $f['buckets'] = array_values(array_filter(array_map(function (array $bucket) use ($facet): ?array {
                        return $facet->normalizeBucket($bucket);
                    }, $f['buckets']), function ($value): bool {
                        return null !== $value;
                    }));
                } catch (\Throwable $e) {
                    throw new \Exception(sprintf('Error normalizing buckets with "%s" facet: %s', $facet::getKey(), $e->getMessage()), 0, $e);
                }
            }

            $type = $this->attributeTypeRegistry->getStrictType($f['meta']['type'] ?? TextAttributeType::NAME);
            try {
                $f['buckets'] = array_values(array_filter(array_map(function (array $bucket) use ($type): ?array {
                    return $type->normalizeBucket($bucket);
                }, $f['buckets']), function ($value): bool {
                    return null !== $value;
                }));
            } catch (\Throwable $e) {
                throw new \Exception(sprintf('Error normalizing buckets with "%s" type: %s', $facet::getKey(), $e->getMessage()), 0, $e);
            }

            if ($type instanceof DateTimeAttributeType) {
                $f['meta']['widget'] = ESFacetInterface::TYPE_DATE_RANGE;
            }

            $mergedFacets[$k] = $f;
        }

        foreach ($missing as $k => $count) {
            $mergedFacets[$k]['missing_count'] = $count;
        }

        return $mergedFacets;
    }
}
