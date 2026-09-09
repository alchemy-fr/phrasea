<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Service\Asset\AssetListPreloader;
use Symfony\Bundle\SecurityBundle\Security;

class AssetCollectionProvider extends AbstractCollectionProvider
{
    public function __construct(
        private readonly AssetSearch $assetSearch,
        private readonly Security $security,
        private readonly AssetListPreloader $assetListPreloader,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        try {
            [$result, $facets, $queryJson, $searchTime] = $this->assetSearch->search($userId, $groupIds, $context['filters'] ?? []);
        } catch (NoWorkspaceAllowedException) {
            return [];
        }

        $this->assetListPreloader->preload($result->getCurrentPageResults());

        $response = new ApiMetaWrapperOutput(new PagerFantaApiPlatformPaginator($result));
        $response->setMeta('facets', $facets);
        $response->setMeta('debug:es', [
            'query' => $queryJson,
            'time' => $searchTime,
        ]);

        return $response;
    }
}
