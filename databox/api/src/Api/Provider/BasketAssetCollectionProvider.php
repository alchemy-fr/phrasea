<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Entity\Basket\BasketAsset;
use App\Repository\Basket\BasketRepository;
use App\Security\AssetAttributeFilterChecker;
use App\Security\Voter\AbstractVoter;

class BasketAssetCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use CollectionProviderAwareTrait;

    public function __construct(
        private readonly BasketRepository $repository,
        private readonly AssetAttributeFilterChecker $attributeFilterChecker,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $basket = DoctrineUtil::findStrictByRepo($this->repository, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $basket);

        $items = $this->collectionProvider->provide($operation, $uriVariables, $context);

        // The output of each item votes on its asset
        if (is_iterable($items)) {
            $assets = [];
            foreach ($items as $item) {
                if ($item instanceof BasketAsset) {
                    $assets[] = $item->getAsset();
                }
            }
            $this->attributeFilterChecker->preloadForCurrentUser($assets);
        }

        return $items;
    }
}
