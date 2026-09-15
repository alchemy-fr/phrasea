<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\Exception\MissingSearchIndexException;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Elasticsearch\SuggestionSearch;
use Psr\Log\LoggerInterface;

class SearchSuggestionCollectionProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly SuggestionSearch $suggestionSearch,
        private readonly LoggerInterface $logger,
    ) {
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
        } catch (MissingSearchIndexException $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

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
