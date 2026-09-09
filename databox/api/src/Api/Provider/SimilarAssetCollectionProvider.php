<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Elasticsearch\SimilarAssetSearch;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;

class SimilarAssetCollectionProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    private const int DEFAULT_LIMIT = 10;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly SimilarAssetSearch $similarAssetSearch,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $asset = $this->assetRepository->find($uriVariables['id']);
        if (!$asset instanceof Asset) {
            return null;
        }
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);

        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        $limit = (int) ($context['filters']['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        try {
            [$assets, $scores] = $this->similarAssetSearch->findSimilar($userId, $groupIds, $asset, $limit);
        } catch (NoWorkspaceAllowedException) {
            $assets = [];
            $scores = [];
        }

        $assets = array_values(array_filter(
            $assets,
            fn (Asset $a): bool => $this->isGranted(AbstractVoter::READ, $a)
        ));

        $response = new ApiMetaWrapperOutput(new \ArrayIterator($assets));
        $response->setMeta('scores', $scores);

        return $response;
    }
}
