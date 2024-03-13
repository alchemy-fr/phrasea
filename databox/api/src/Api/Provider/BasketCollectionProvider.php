<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\BasketSearch;
use Symfony\Bundle\SecurityBundle\Security;

class BasketCollectionProvider extends AbstractCollectionProvider
{
    public function __construct(private readonly BasketSearch $basketSearch, private readonly Security $security)
    {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        return $this->basketSearch->search($userId, $groupIds, $context['filters'] ?? []);
    }
}
