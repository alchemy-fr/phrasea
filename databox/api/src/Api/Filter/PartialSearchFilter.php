<?php

declare(strict_types=1);

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter as OrmPartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use App\Elasticsearch\Filter\ElasticsearchFilterInterface;
use App\Elasticsearch\Filter\EsFieldTrait;
use App\Elasticsearch\Filter\SearchQuery;
use Doctrine\ORM\QueryBuilder;
use Elastica\Query;

/**
 * The canonical PartialSearchFilter ("contains" match, `LIKE %value%`), also applied on Elasticsearch.
 *
 * On Elasticsearch, the match is a `*value*` wildcard on the `es_field` of the parameter, which
 * must be a keyword field (e.g. `value.raw`), case-insensitive unless the filter is case-sensitive.
 */
final class PartialSearchFilter implements FilterInterface, OpenApiParameterFilterInterface, ElasticsearchFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use EsFieldTrait;

    private readonly OrmPartialSearchFilter $ormFilter;

    public function __construct(
        private readonly bool $caseSensitive = false,
    ) {
        $this->ormFilter = new OrmPartialSearchFilter($caseSensitive);
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->ormFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    public function applyToElasticsearch(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'];
        $values = $parameter->getValue();

        // associative arrays are operator-maps owned by ComparisonFilter/DateFilter, not a text match
        if (\is_array($values) && !array_is_list($values)) {
            return;
        }

        $values = array_values(array_filter(
            \is_array($values) ? $values : [$values],
            static fn (mixed $value): bool => \is_string($value) && '' !== $value,
        ));
        if (empty($values)) {
            return;
        }

        $field = self::getEsField($parameter);
        $wildcards = array_map(
            fn (string $value): Query\Wildcard => (new Query\Wildcard($field, '*'.self::escapeWildcard($value).'*'))
                ->setCaseInsensitive(!$this->caseSensitive),
            $values,
        );

        if (1 === \count($wildcards)) {
            $query->bool->addFilter($wildcards[0]);

            return;
        }

        $anyOf = new Query\BoolQuery();
        $anyOf->setMinimumShouldMatch(1);
        foreach ($wildcards as $wildcard) {
            $anyOf->addShould($wildcard);
        }
        $query->bool->addFilter($anyOf);
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter|array|null
    {
        return $this->ormFilter->getOpenApiParameters($parameter);
    }

    private static function escapeWildcard(string $value): string
    {
        return addcslashes($value, '\\*?');
    }
}
