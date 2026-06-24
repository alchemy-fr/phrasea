<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Collection;
use App\Repository\Core\CollectionRepository;

final readonly class AclCollectionIndexUpdateService
{
    public function __construct(
        private ElasticSearchClient $elasticSearchClient,
        private CollectionRepository $collectionRepository,
    ) {
    }

    public function addAllowedUserOrGroupToWorkspace(?string $workspaceId, int $userType, string $userId): void
    {
        $query = [];
        if (null !== $workspaceId) {
            $query['term'] = [
                'workspaceId' => $workspaceId,
            ];
        }

        $this->addAllowedUserOrGroup($userType, $userId, $query);
    }

    public function addAllowedUserOrGroupToCollection(string $collectionId, int $userType, string $userId): void
    {
        /** @var Collection|null $collection */
        $collection = $this->collectionRepository->find($collectionId);
        if (null === $collection) {
            return;
        }

        $this->addAllowedUserOrGroup($userType, $userId, [
            'term' => [
                'absolutePath' => $collection->getAbsolutePath(),
            ],
        ]);
    }

    private function addAllowedUserOrGroup(int $userType, string $userId, array $query): void
    {
        $kind = AccessControlEntryInterface::TYPE_USER_VALUE === $userType ? 'users' : 'groups';

        $this->elasticSearchClient->updateByQuery('collection', $query, [
            'lang' => 'painless',
            'source' => <<<EOF
ArrayList list = ctx._source.$kind != null ? ctx._source.$kind : new ArrayList();
if (!list.contains(params['uid'])) {
    list.add(params['uid']);
    ctx._source.$kind = list;
}
EOF,
            'params' => [
                'uid' => $userId,
            ],
        ]);
    }
}
