<?php

declare(strict_types=1);

namespace App\Elasticsearch\Filter;

use ApiPlatform\Metadata\Operation;

/**
 * A query parameter filter that also applies to an Elasticsearch search.
 *
 * Counterpart of the ORM {@see \ApiPlatform\Doctrine\Orm\Filter\FilterInterface}: a filter
 * implementing both is declared once on the QueryParameter and applied by the engine the
 * provider ends up using. The Elasticsearch field is read from the parameter's
 * `extraProperties[ES_FIELD]`, falling back to the ORM property, then to the key.
 */
interface ElasticsearchFilterInterface
{
    final public const string ES_FIELD = 'es_field';

    /**
     * @param array<string, mixed> $context same shape as the ORM FilterInterface::apply() context:
     *                                      `['filters' => [<property|key> => value], 'parameter' => Parameter, ...]`
     */
    public function applyToElasticsearch(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void;
}
