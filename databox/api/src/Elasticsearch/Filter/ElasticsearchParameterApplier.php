<?php

declare(strict_types=1);

namespace App\Elasticsearch\Filter;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies the operation's query parameter filters to an Elasticsearch query.
 *
 * Mirrors {@see \ApiPlatform\Doctrine\Orm\Extension\ParameterExtension}: every parameter
 * holding a value and declaring a filter is resolved (instance or filter locator) and
 * applied when the filter implements {@see ElasticsearchFilterInterface}.
 */
final class ElasticsearchParameterApplier
{
    public function __construct(
        #[Autowire(service: 'api_platform.filter_locator')]
        private readonly ContainerInterface $filterLocator,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function apply(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        foreach ($operation?->getParameters() ?? [] as $parameter) {
            $value = $parameter->getValue();
            if (null === $value || $value instanceof ParameterNotFound) {
                continue;
            }

            if (null === $filterId = $parameter->getFilter()) {
                continue;
            }

            $filter = match (true) {
                $filterId instanceof ElasticsearchFilterInterface => $filterId,
                \is_string($filterId) && $this->filterLocator->has($filterId) => $this->filterLocator->get($filterId),
                default => null,
            };
            if (!$filter instanceof ElasticsearchFilterInterface) {
                continue;
            }

            $key = $parameter->getProperty() ?? $parameter->getKey();
            $filter->applyToElasticsearch($query, $resourceClass, $operation, [
                'filters' => [$key => $value],
                'parameter' => $parameter,
            ] + $context);
        }
    }
}
