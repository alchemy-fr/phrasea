<?php

declare(strict_types=1);

namespace App\Elasticsearch\Filter;

use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Elastica\Query;

/**
 * Search-as-you-type query on a `search_as_you_type` field (`<field>.suggest`).
 *
 * Elasticsearch only: a collection declaring it switches to Elasticsearch when the
 * parameter is set (see TagCollectionProvider).
 */
final class SuggestQueryFilter implements ElasticsearchFilterInterface, OpenApiParameterFilterInterface
{
    use EsFieldTrait;

    public function applyToElasticsearch(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'];
        $value = $parameter->getValue(null);
        if (!\is_string($value) || '' === $queryString = trim($value)) {
            return;
        }

        $field = self::getEsField($parameter);
        $match = new Query\MultiMatch();
        $match->setQuery($queryString);
        $match->setType('bool_prefix');
        $match->setFields([
            $field.'.suggest',
            $field.'.suggest._2gram',
            $field.'.suggest._3gram',
        ]);

        $query->bool->addMust($match);
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        return new OpenApiParameter(
            name: $parameter->getKey(),
            in: 'query',
            description: sprintf('Search-as-you-type query on "%s"', self::getEsField($parameter)),
            schema: $parameter->getSchema() ?? ['type' => 'string'],
        );
    }
}
