<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use Elastica\Aggregation\Missing;
use Elastica\Query;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FacetHandler
{
    public const string MISSING_SUFFIX = '::missing';

    public function __construct(
        private BuiltInAttributeRegistry $builtInAttributeRegistry,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private TranslatorInterface $translator,
    ) {
    }

    public function addBuiltInFacets(Query $query): void
    {
        foreach ($this->builtInAttributeRegistry->getAll() as $item) {
            if (!$item->isFacet()) {
                continue;
            }

            $item->buildFacet($query, $this->translator);
            if ($item->includesMissing()) {
                $missingAgg = new Missing($item::getKey().self::MISSING_SUFFIX, $item::getName());
                $query->addAggregation($missingAgg);
            }
        }
    }

    public function normalizeBuckets(array $facets): array
    {
        $missing = [];
        $mergedFacets = [];
        foreach ($facets as $k => $f) {
            if (str_ends_with((string) $k, self::MISSING_SUFFIX)) {
                $k = substr((string) $k, 0, -strlen(self::MISSING_SUFFIX));

                $missing[$k] = $f['doc_count'];

                continue;
            }

            $builtInAttribute = $this->builtInAttributeRegistry->getBuiltInAttribute($k);
            if ($builtInAttribute) {
                try {
                    $f['buckets'] = array_values(array_filter($builtInAttribute->normalizeBuckets($f['buckets']), fn ($value): bool => null !== $value));
                } catch (\Throwable $e) {
                    throw new \Exception(sprintf('Error normalizing buckets with "%s" facet: %s', $builtInAttribute::getKey(), $e->getMessage()), 0, $e);
                }
            }

            $type = $this->attributeTypeRegistry->getStrictType($f['meta']['type'] ?? TextAttributeType::NAME);
            try {
                $f['buckets'] = array_values(array_filter($type->normalizeBuckets($f['buckets']), fn ($value): bool => null !== $value));
            } catch (\Throwable $e) {
                throw new \Exception(sprintf('Error normalizing buckets with "%s" type: %s', $builtInAttribute::getKey(), $e->getMessage()), 0, $e);
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
