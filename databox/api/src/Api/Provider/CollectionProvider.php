<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\CollectionSearch;

class CollectionProvider extends AbstractCollectionProvider
{
    public function __construct(private readonly CollectionSearch $search)
    {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array|object {
        $filters = $context['filters'] ?? [];

        if ($filters['groupByWorkspace'] ?? false) {
            return $this->search->searchAggregationsByWorkspace($context['userId'], $context['groupIds'], $filters);
        }

        $result = $this->search->search($context['userId'], $context['groupIds'], $filters);

        return new PagerFantaApiPlatformPaginator($result);
    }
}
