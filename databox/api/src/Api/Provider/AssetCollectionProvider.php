<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\AssetSearch;
use Symfony\Bundle\SecurityBundle\Security;

class AssetCollectionProvider extends AbstractCollectionProvider
{
    public function __construct(private readonly AssetSearch $assetSearch, private readonly Security $security)
    {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        [$result, $facets, $queryJson, $searchTime] = $this->assetSearch->search($userId, $groupIds, $context['filters'] ?? []);

        $response = new ApiMetaWrapperOutput(new PagerFantaApiPlatformPaginator($result));
        $response->setMeta('facets', $facets);
        $response->setMeta('debug:es', [
            'query' => $queryJson,
            'time' => $searchTime,
        ]);

        return $response;
    }
}
