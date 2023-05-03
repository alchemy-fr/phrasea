<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Elasticsearch\AssetDataTemplateSearch;
use App\Entity\Template\AssetDataTemplate;
use Symfony\Component\Security\Core\Security;

class AssetDataTemplateCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(private readonly AssetDataTemplateSearch $search, private readonly Security $security)
    {
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $user = $this->security->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        return $this->search->search($userId, $groupIds, $context['filters'] ?? []);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return AssetDataTemplate::class === $resourceClass;
    }
}
