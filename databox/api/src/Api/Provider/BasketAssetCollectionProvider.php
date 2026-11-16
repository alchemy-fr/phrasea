<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Entity\Basket\BasketAsset;
use App\Repository\Basket\BasketRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\VisibleAssetResolver;

class BasketAssetCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use CollectionProviderAwareTrait;

    public function __construct(
        private readonly BasketRepository $repository,
        private readonly VisibleAssetResolver $visibleAssetResolver,
    ) {
    }

    /**
     * The items are basket rows, paginated in SQL. The assets the user may not see in a
     * listing (attribute filter rules) are rendered as stubs by the AssetOutputMapper:
     * their visibility is resolved here for the whole page at once.
     */
    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $basket = DoctrineUtil::findStrictByRepo($this->repository, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $basket);

        $result = $this->collectionProvider->provide($operation, $uriVariables, $context);
        if (!is_iterable($result)) {
            return $result;
        }

        /** @var BasketAsset[] $items */
        $items = iterator_to_array($result, false);
        $this->visibleAssetResolver->filterVisible(array_map(static fn (BasketAsset $item) => $item->getAsset(), $items));

        if ($result instanceof PaginatorInterface) {
            return new TraversablePaginator(
                new \ArrayIterator($items),
                $result->getCurrentPage(),
                $result->getItemsPerPage(),
                $result->getTotalItems(),
            );
        }

        return $items;
    }
}
