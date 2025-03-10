<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Asset;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;

final class AssetAttributeBatchUpdateProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param AssetAttributeBatchUpdateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $uriVariables['id']);
        $this->denyAccessUnlessGranted(AssetVoter::EDIT_ATTRIBUTES, $asset);

        $this->batchAttributeManager->validate($asset->getWorkspaceId(), [$asset->getId()], $data);

        $this->batchAttributeManager->handleBatch(
            $asset->getWorkspaceId(),
            [$asset->getId()],
            $data,
            $this->getStrictUser(),
            true,
        );

        $this->em->clear();

        return $asset;
    }
}
