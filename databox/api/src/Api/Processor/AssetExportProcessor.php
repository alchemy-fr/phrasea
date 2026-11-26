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
use App\Model\AuditLogActionEnum;
use App\Security\AssetAttributeFilterChecker;
use App\Security\Voter\AbstractVoter;
use App\Service\Log\AuditLogManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AssetExportProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogManager $auditLogManager,
        private readonly AssetAttributeFilterChecker $attributeFilterChecker,
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
        $basketIds = [];
        if (null !== $basketAssetIds = $data->getBasketAssets()) {
            $assetIds = [];
            /** @var BasketAsset[] $basketAssets */
            $basketAssets = array_map(
                fn ($basketAssetId): BasketAsset => DoctrineUtil::findStrict($this->em, BasketAsset::class, $basketAssetId, throw404: true),
                $basketAssetIds,
            );
            $this->attributeFilterChecker->preloadForCurrentUser(array_map(static fn (BasketAsset $basketAsset): Asset => $basketAsset->getAsset(), $basketAssets));
            foreach ($basketAssets as $basketAsset) {
                $this->denyAccessUnlessGranted(AbstractVoter::READ, $basketAsset->getBasket());
                $this->denyAccessUnlessGranted(AbstractVoter::READ, $basketAsset->getAsset());
                $assetIds[$basketAsset->getAsset()->getId()] = true;
                $basketIds[$basketAsset->getBasket()->getId()] = true;
            }
            $data->setBasketAssets(array_values(array_unique($basketAssetIds)));
            $data->setAssets(array_keys($assetIds));
        }

        $assets = array_map(
            fn ($assetId): Asset => DoctrineUtil::findStrict($this->em, Asset::class, $assetId),
            $data->getAssets(),
        );
        $this->attributeFilterChecker->preloadForCurrentUser($assets);
        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
        }

        $user = $this->getStrictUser();
        $data->setOwnerId($user->getId());
        $data->setUserData($this->extractUserData());

        $this->em->persist($data);
        foreach ($assets as $asset) {
            $this->auditLogManager->log(AuditLogActionEnum::AssetDownloaded, $asset, array_filter([
                'exportId' => $data->getId(),
                'renditions' => $data->getRenditions(),
                'basketIds' => array_keys($basketIds),
            ]));
        }
        $this->em->flush();

        $this->bus->dispatch(new AssetExportProcess($data->getId()));

        return $this->em->find($data::class, $data->getId());
    }
}
