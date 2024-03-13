<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\BasketSearch;
use App\Util\SecurityAwareTrait;
use Symfony\Bundle\SecurityBundle\Security;

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
