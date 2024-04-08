<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\BasketSearch;
use App\Util\SecurityAwareTrait;

class BasketCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    public function __construct(private readonly BasketSearch $basketSearch)
    {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $user = $this->getStrictUser();

        return $this->basketSearch->search($user->getId(), $user->getGroups(), $context['filters'] ?? []);
    }
}
