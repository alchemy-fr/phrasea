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
        $pagination = $this->search->search($context['userId'], $context['groupIds'], $context['filters'] ?? []);

        return new PagerFantaApiPlatformPaginator($pagination);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Collection::class === $resourceClass;
    }

}
