<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\SuggestionSearch;
use App\Util\SecurityAwareTrait;

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

        [$result, $queryJson, $searchTime] = $this->suggestionSearch->search($userId, $groupIds, $context['filters'] ?? []);

        $response = new ApiMetaWrapperOutput(new PagerFantaApiPlatformPaginator($result));
        $response->setMeta('debug:es', [
            'query' => $queryJson,
            'time' => $searchTime,
        ]);

        return $response;
    }
}
