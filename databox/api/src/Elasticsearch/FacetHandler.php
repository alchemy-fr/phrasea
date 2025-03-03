<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Facet\FacetRegistry;
use Elastica\Aggregation\Missing;
use Elastica\Query;

final readonly class FacetHandler
{
    public const string MISSING_SUFFIX = '::missing';

    public function __construct(private FacetRegistry $facetRegistry, private AttributeTypeRegistry $attributeTypeRegistry)
    {
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
                    $f['buckets'] = array_values(array_filter(array_map(fn (array $bucket): ?array => $facet->normalizeBucket($bucket), $f['buckets']), fn ($value): bool => null !== $value));
                } catch (\Throwable $e) {
                    throw new \Exception(sprintf('Error normalizing buckets with "%s" facet: %s', $facet::getKey(), $e->getMessage()), 0, $e);
                }
            }

            $type = $this->attributeTypeRegistry->getStrictType($f['meta']['type'] ?? TextAttributeType::NAME);
            try {
                $f['buckets'] = array_values(array_filter(array_map(fn (array $bucket): ?array => $type->normalizeBucket($bucket), $f['buckets']), fn ($value): bool => null !== $value));
            } catch (\Throwable $e) {
                throw new \Exception(sprintf('Error normalizing buckets with "%s" type: %s', $facet::getKey(), $e->getMessage()), 0, $e);
            }

            if (ESFacetInterface::TYPE_TEXT !== $widget = $type->getFacetType()) {
                $f['meta']['widget'] = $widget;
            }

            $mergedFacets[$k] = $f;
        }

        foreach ($missing as $k => $count) {
            $mergedFacets[$k]['missing_count'] = $count;
        }

        return $mergedFacets;
    }
}
