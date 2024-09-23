<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\AssetDataTemplateSearch;
use Symfony\Bundle\SecurityBundle\Security;

class AssetDataTemplateCollectionProvider extends AbstractCollectionProvider
{
    public function __construct(private readonly AssetDataTemplateSearch $search, private readonly Security $security)
    {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        return $this->search->search($userId, $groupIds, $context['filters'] ?? []);
    }
}
