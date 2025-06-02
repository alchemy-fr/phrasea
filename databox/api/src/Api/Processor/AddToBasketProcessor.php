<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AddToBasketInput;
use App\Api\Model\Input\AssetToBasketInput;
use App\Consumer\Handler\Basket\BasketUpdate;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Repository\Basket\BasketRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AddToBasketProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BasketRepository $basketRepository,
        private readonly IriConverterInterface $iriConverter,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param AddToBasketInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Basket
    {
        $user = $this->getStrictUser();
        if (isset($uriVariables['id'])) {
            $basketId = $uriVariables['id'];
            $basket = DoctrineUtil::findStrictByRepo($this->basketRepository, $basketId);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $basket);
        } else {
            $basket = $this->basketRepository->findOneBy([
                'ownerId' => $user->getId(),
            ], [
                'createdAt' => 'ASC',
            ]);
        }

        if (null === $basket) {
            $basket = new Basket();
            $basket->setOwnerId($user->getId());
            $this->em->persist($basket);
            $position = 0;
        } else {
            $position = $this->basketRepository->getMaxPosition($basket->getId()) + 1;
        }

        $mapping = [];
        $ids = array_map(function (AssetToBasketInput $input) use (&$mapping): string {
            $mapping[$input->id] = $input;

            return $input->id;
        }, $data->assets);

        $assets = $this->em->getRepository(Asset::class)
            ->findByIds($ids);

        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
            $basketItem = new BasketAsset();
            $basketItem->setBasket($basket);
            $basketItem->setOwnerId($user->getId());
            $basketItem->setAsset($asset);
            $basketItem->setPosition($position++);
            $this->em->persist($basketItem);
        }

        $this->em->flush();

        $this->bus->dispatch(new BasketUpdate($basket->getId()));

        return $basket;
    }
}
