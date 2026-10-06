<?php

declare(strict_types=1);

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter as OrmSortFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\JsonSchemaFilterInterface;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\Metadata\SortFilterInterface;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use App\Elasticsearch\Filter\ElasticsearchFilterInterface;
use App\Elasticsearch\Filter\EsFieldTrait;
use App\Elasticsearch\Filter\SearchQuery;
use Doctrine\ORM\QueryBuilder;

/**
 * The canonical SortFilter (`order[property]=asc|desc`), also applied on Elasticsearch.
 *
 * On Elasticsearch, the sort clause targets the `es_field` of the parameter, which must be
 * sortable (a keyword sub-field such as `value.raw`, a date or a number).
 */
final class SortFilter implements FilterInterface, JsonSchemaFilterInterface, OpenApiParameterFilterInterface, SortFilterInterface, ElasticsearchFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use EsFieldTrait;

    private readonly OrmSortFilter $ormFilter;

    public function __construct(?string $nullsComparison = null)
    {
        $this->ormFilter = new OrmSortFilter($nullsComparison);
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->ormFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    public function applyToElasticsearch(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'];
        $value = $parameter->getValue(null);
        if (!\is_string($value)) {
            return;
        }

        $direction = strtolower($value);
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            return;
        }

        $query->addSort([self::getEsField($parameter) => $direction]);
    }

    public function getSchema(Parameter $parameter): array
    {
        return $this->ormFilter->getSchema($parameter);
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter|array|null
    {
        return $this->ormFilter->getOpenApiParameters($parameter);
    }
}
