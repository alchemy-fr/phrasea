<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Elasticsearch\SuggestionSearch;

class SearchSuggestionCollectionProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(private readonly SuggestionSearch $suggestionSearch)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->getUser();
        $userId = $user?->getId();
        $groupIds = $user?->getGroups() ?? [];

        try {
            [$result, $queryJson, $searchTime] = $this->suggestionSearch->search($userId, $groupIds, $context['filters'] ?? []);
        } catch (NoWorkspaceAllowedException) {
            return [];
        }

        $response = new ApiMetaWrapperOutput(new PagerFantaApiPlatformPaginator($result));
        $response->setMeta('debug:es', [
            'query' => $queryJson,
            'time' => $searchTime,
        ]);

        return $response;
    }
}
