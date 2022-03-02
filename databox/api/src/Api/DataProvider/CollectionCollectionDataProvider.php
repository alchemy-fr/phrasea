<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;

class CollectionCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private CollectionSearch $search;

    public function __construct(CollectionSearch $search)
    {
        $this->search = $search;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $filters = $context['filters'] ?? [];

        if ($filters['groupByWorkspace'] ?? false) {
            return $this->search->searchAggregationsByWorkspace($context['userId'], $context['groupIds'], $filters);
        }

        $result = $this->search->search($context['userId'], $context['groupIds'], $filters);

        return new PagerFantaApiPlatformPaginator($result);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Collection::class === $resourceClass;
    }
}
