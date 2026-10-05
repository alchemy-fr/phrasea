<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AssetRendition;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;

class RenditionCollectionProvider extends AbstractAssetFilteredCollectionProvider
{
    public function __construct(
        private readonly AssetPolicyManager $assetPolicyManager,
    ) {
    }

    public function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $asset = $this->getAsset($context);

        $renditions = $this->em->getRepository(AssetRendition::class)
            ->createQueryBuilder('t')
            ->andWhere('t.asset = :a')
            ->setParameter('a', $asset->getId())
            ->leftJoin('t.definition', 'd')
            ->addOrderBy('d.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $assetPolicyFilter = $this->assetPolicyManager->getPolicyApplicationFilter($asset);

        return array_filter($renditions, function (AssetRendition $rendition) use ($assetPolicyFilter): bool {
            if (!$this->security->isGranted(AbstractVoter::READ, $rendition)) {
                return false;
            }

            $definition = $rendition->getDefinition();

            return null === $definition || !in_array($definition->getId(), $assetPolicyFilter->getFilteredRenditions(), true);
        });
    }
}
