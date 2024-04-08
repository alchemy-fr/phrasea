<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Repository\Basket\BasketRepository;
use App\Security\Voter\AbstractVoter;
use App\Util\DoctrineUtil;
use App\Util\SecurityAwareTrait;

class BasketAssetCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use CollectionProviderAwareTrait;

    public function __construct(private readonly BasketRepository $basketRepository)
    {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $basket = DoctrineUtil::findStrictByRepo($this->basketRepository, $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $basket);

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
