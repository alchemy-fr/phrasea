<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\RemoveFromBasketInput;
use App\Consumer\Handler\Basket\BasketUpdate;
use App\Entity\Basket\Basket;
use App\Repository\Basket\BasketRepository;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\Messenger\MessageBusInterface;

class RemoveFromBasketProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly BasketRepository $basketRepository,
        private readonly IriConverterInterface $iriConverter,
        private readonly MessageBusInterface $bus,
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

        $this->bus->dispatch(new BasketUpdate($basket->getId()));

        return $basket;
    }
}
