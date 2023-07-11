<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\AssetSearch;
use App\Entity\Core\Asset;
use Symfony\Bundle\SecurityBundle\Security;

class AssetCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(private readonly AssetSearch $assetSearch, private readonly Security $security)
    {
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $user = $this->security->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        [$result, $facets, $queryJson, $searchTime] = $this->assetSearch->search($userId, $groupIds, $context['filters'] ?? []);

        $response = new ApiMetaWrapperOutput(new PagerFantaApiPlatformPaginator($result));
        $response->setMeta('facets', $facets);
        $response->setMeta('debug:es', [
            'query' => $queryJson,
            'time' => $searchTime,
        ]);

        return $response;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Asset::class === $resourceClass;
    }
}
