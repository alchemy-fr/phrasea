<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use Alchemy\AclBundle\UserInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use Symfony\Component\Security\Core\Security;

class CollectionCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private CollectionSearch $search;
    /**
     * @var Security
     */
    private Security $security;

    public function __construct(CollectionSearch $search, Security $security)
    {
        $this->search = $search;
        $this->security = $security;
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
        return Collection::class === $resourceClass;
    }

}
