<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AddToBasketInput;
use App\Api\Model\Input\AssetToBasketInput;
use App\Api\Model\Input\RemoveFromBasketInput;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Repository\Basket\BasketRepository;
use App\Security\Voter\AbstractVoter;
use App\Util\DoctrineUtil;
use App\Util\SecurityAwareTrait;
use Doctrine\ORM\EntityManagerInterface;

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

        dump($data);
        $this->basketRepository->removeFromBasket($basketId, $data->items);

        return $basket;
    }
}
