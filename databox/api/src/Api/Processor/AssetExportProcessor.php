<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Consumer\Handler\Asset\AssetExportProcess;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetExport;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AssetExportProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param AssetExport $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): AssetExport {
        if (null !== $basketAssetIds = $data->getBasketAssets()) {
            $assetIds = [];
            foreach ($basketAssetIds as $basketAssetId) {
                /** @var BasketAsset $basketAsset */
                $basketAsset = DoctrineUtil::findStrict($this->em, BasketAsset::class, $basketAssetId, throw404: true);
                $this->denyAccessUnlessGranted(AbstractVoter::READ, $basketAsset->getBasket());
                $this->denyAccessUnlessGranted(AbstractVoter::READ, $basketAsset->getAsset());
                $assetIds[$basketAsset->getAsset()->getId()] = true;
            }
            $data->setBasketAssets(array_values(array_unique($basketAssetIds)));
            $data->setAssets(array_keys($assetIds));
        }

        foreach ($data->getAssets() as $assetId) {
            $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
        }

        $user = $this->getStrictUser();
        $data->setOwnerId($user->getId());
        $data->setUserData($this->extractUserData());

        $this->em->persist($data);
        $this->em->flush();

        $this->bus->dispatch(new AssetExportProcess($data->getId()));

        return $this->em->find($data::class, $data->getId());
    }
}
