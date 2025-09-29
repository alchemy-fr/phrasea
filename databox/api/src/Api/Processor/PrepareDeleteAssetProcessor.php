<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\PrepareDeleteAssetsInput;
use App\Api\Model\Output\PrepareDeleteAssetsOutput;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\ShareRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class PrepareDeleteAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetRepository $assetRepository,
        private readonly ShareRepository $shareRepository,
    ) {
    }

    /**
     * @param PrepareDeleteAssetsInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): PrepareDeleteAssetsOutput
    {
        $assets = $this->assetRepository->findByIds($data->ids);

        $canDelete = false;
        $cache = [];
        $collections = [];
        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
            if (!$canDelete && $this->isGranted(AbstractVoter::DELETE, $asset)) {
                $canDelete = true;
            }
            foreach ($asset->getCollections() as $assetCollection) {
                $c = $assetCollection->getCollection();
                $cId = $c->getId();
                if ($cId !== $assetCollection->getAsset()->getReferenceCollectionId()) {
                    if ($cache[$cId] ?? ($cache[$cId] = $this->security->isGranted(AbstractVoter::EDIT, $c))) {
                        $collections[$cId] = $c;
                    }
                }
            }
        }

        $shareCount = $this->shareRepository->getShareCount($data->ids);

        return new PrepareDeleteAssetsOutput($canDelete, array_values($collections), $shareCount);
    }
}
