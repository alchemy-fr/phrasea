<?php

declare(strict_types=1);

namespace App\Elasticsearch\Filter;

use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Elastica\Query;

/**
 * Search-as-you-type query on a `search_as_you_type` field (`<field>.suggest`), and on the
 * additional fields given to the constructor.
 *
 * Elasticsearch only: a collection declaring it switches to Elasticsearch when the
 * parameter is set (see TagCollectionProvider).
 */
final class SuggestQueryFilter implements ElasticsearchFilterInterface, OpenApiParameterFilterInterface
{
    use EsFieldTrait;

    /**
     * @param list<string> $additionalFields other ES fields searched as well, each with a
     *                                       `search_as_you_type` `suggest` sub-field
     */
    public function __construct(
        private readonly array $additionalFields = [],
    ) {
    }

    public function applyToElasticsearch(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'];
        $value = $parameter->getValue(null);
        if (!\is_string($value) || '' === $queryString = trim($value)) {
            return;
        }

        $fields = [];
        foreach ($this->getFields($parameter) as $field) {
            array_push($fields, $field.'.suggest', $field.'.suggest._2gram', $field.'.suggest._3gram');
        }

        $match = new Query\MultiMatch();
        $match->setQuery($queryString);
        $match->setType('bool_prefix');
        $match->setFields($fields);

        $query->bool->addMust($match);
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        return new OpenApiParameter(
            name: $parameter->getKey(),
            in: 'query',
            description: sprintf('Search-as-you-type query on "%s"', implode('", "', $this->getFields($parameter))),
            schema: $parameter->getSchema() ?? ['type' => 'string'],
        );
    }

    /**
     * @return list<string>
     */
    private function getFields(Parameter $parameter): array
    {
        return [self::getEsField($parameter), ...$this->additionalFields];
    }
}
