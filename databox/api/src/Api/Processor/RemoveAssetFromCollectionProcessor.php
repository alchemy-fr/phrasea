<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\CollectionAsset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class RemoveAssetFromCollectionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $assetCollection = $this->em->getRepository(CollectionAsset::class)
            ->findOneBy(['asset' => $data->getId(), 'collection' => $uriVariables['collectionId']]);

        if ($assetCollection instanceof CollectionAsset) {
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $assetCollection->getCollection());
            if ($assetCollection->getAsset()->getReferenceCollection()?->getId() === $assetCollection->getCollection()->getId()) {
                throw new \InvalidArgumentException('Cannot remove asset from reference collection');
            }
            $this->em->remove($assetCollection);
            $this->em->flush();
        }
    }
}
