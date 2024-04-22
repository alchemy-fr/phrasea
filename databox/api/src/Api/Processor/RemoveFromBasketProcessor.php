<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\RemoveFromBasketInput;
use App\Entity\Basket\Basket;
use App\Repository\Basket\BasketRepository;
use App\Security\Voter\AbstractVoter;

class RemoveFromBasketProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    /**
     * @param RemoveFromBasketInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Basket
    {
        $basketId = $uriVariables['id'];
        $basket = DoctrineUtil::findStrictByRepo($this->basketRepository, $basketId);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $basket);

        $this->basketRepository->removeFromBasket($basketId, $data->items);

        return $basket;
    }
}
