<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Basket\Basket;
use App\Repository\Basket\BasketRepository;
use Doctrine\ORM\EntityManagerInterface;

class ArchiveBasketProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BasketRepository $basketRepository,
    ) {
    }

    public function process($data, $operation, array $uriVariables = [], array $context = []): Basket
    {
        $basketId = $uriVariables['id'];
        /** @var Basket $basket */
        $basket = DoctrineUtil::findStrictByRepo($this->basketRepository, $basketId);

        $basket->archive();

        $this->em->persist($basket);
        $this->em->flush();

        return $basket;
    }
}
