<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\CollectionSearch;

class CollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly CollectionSearch $search,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $result = $this->search->search($context['userId'], $context['groupIds'], $context['filters'] ?? []);

        return new PagerFantaApiPlatformPaginator($result);
    }
}
