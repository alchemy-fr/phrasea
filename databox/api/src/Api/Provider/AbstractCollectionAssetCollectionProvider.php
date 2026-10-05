<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\BuiltInAttribute\PositionBuiltInAttribute;
use App\Elasticsearch\MappedPager;
use App\Elasticsearch\NoWorkspaceAllowedException;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Security\Voter\AbstractVoter;
use Pagerfanta\Pagerfanta;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Lists the relations of a collection or a story in their stored order.
 *
 * Which assets are visible is decided by AssetSearch, so that these endpoints
 * get exactly the same access rules as the regular asset search: workspace and
 * object ACL, privacy, private-collection masking, tag filter rules, deleted and
 * quarantined assets. The relations are then attached to the resulting page, to
 * expose each asset's position.
 */
abstract class AbstractCollectionAssetCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    protected AssetSearch $assetSearch;

    /**
     * @return array{Collection, array<string, mixed>} the target collection, and the
     *                                                 AssetSearch filter narrowing the search down to it
     */
    abstract protected function resolveTarget(array $uriVariables): array;

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        [$collection, $scope] = $this->resolveTarget($uriVariables);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $collection);

        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        $options = array_merge($context['filters'] ?? [], $scope, [
            'order' => [PositionBuiltInAttribute::getKey() => 'ASC'],
        ]);

        try {
            [$result] = $this->assetSearch->search($userId, $groupIds, $options);
        } catch (NoWorkspaceAllowedException) {
            return [];
        }

        $pager = new Pagerfanta(new MappedPager(
            fn (array $assets): array => $this->attachRelations($collection->getId(), $assets),
            $result->getAdapter(),
        ));
        $pager->setMaxPerPage($result->getMaxPerPage());
        $pager->setAllowOutOfRangePages(true);
        $pager->setCurrentPage($result->getCurrentPage());

        return new PagerFantaApiPlatformPaginator($pager);
    }

    /**
     * @param Asset[] $assets
     *
     * @return CollectionAsset[]
     */
    private function attachRelations(string $collectionId, array $assets): array
    {
        if ([] === $assets) {
            return [];
        }

        $relations = [];
        foreach ($this->em->getRepository(CollectionAsset::class)->findBy([
            'collection' => $collectionId,
            'asset' => array_map(fn (Asset $asset): string => $asset->getId(), $assets),
        ]) as $relation) {
            $relations[$relation->getAsset()->getId()] = $relation;
        }

        // A relation removed since the last indexation has no row left: skip it
        return array_values(array_filter(array_map(
            fn (Asset $asset): ?CollectionAsset => $relations[$asset->getId()] ?? null,
            $assets,
        )));
    }

    #[Required]
    public function setAssetSearch(AssetSearch $assetSearch): void
    {
        $this->assetSearch = $assetSearch;
    }
}
