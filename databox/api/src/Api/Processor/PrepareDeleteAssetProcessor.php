<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\PrepareDeleteAssetsInput;
use App\Api\Model\Output\PrepareDeleteAssetsOutput;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class PrepareDeleteAssetProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    /**
     * @param PrepareDeleteAssetsInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $assets = $this->assetRepository->findByIds($data->ids);

        $cache = [];
        $collections = [];
        foreach ($assets as $asset) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);
            foreach ($asset->getCollections() as $collection) {
                $cId = $collection->getId();
                if ($cache[$cId] ?? ($cache[$cId] = $this->security->isGranted(AbstractVoter::EDIT, $collection))) {
                    $collections[$cId] = $collection;
                }
            }
        }

        return new PrepareDeleteAssetsOutput(array_values($collections));
    }
}
