<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use Alchemy\AclBundle\UserInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Elasticsearch\AssetSearch;
use App\Entity\Core\Asset;
use Symfony\Component\Security\Core\Security;

class AssetCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private AssetSearch $assetSearch;
    /**
     * @var Security
     */
    private Security $security;

    public function __construct(AssetSearch $assetSearch, Security $security)
    {
        $this->assetSearch = $assetSearch;
        $this->security = $security;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $user = $this->security->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        return $this->assetSearch->search($context['filters']['q'] ?? null, $userId, $groupIds);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Asset::class === $resourceClass;
    }

}
