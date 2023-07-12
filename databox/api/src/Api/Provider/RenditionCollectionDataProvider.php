<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AssetRendition;
use App\Security\Voter\RenditionVoter;

class RenditionCollectionDataProvider extends AbstractAssetFilteredCollectionDataProvider
{
    public function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $asset = $this->getAsset($context);

        $renditions = $this->em->getRepository(AssetRendition::class)
            ->createQueryBuilder('t')
            ->andWhere('t.asset = :a')
            ->setParameter('a', $asset->getId())
            ->innerJoin('t.definition', 'd')
            ->addOrderBy('d.priority', 'DESC')
            ->getQuery()
            ->getResult();

        return array_filter($renditions, fn (AssetRendition $rendition): bool => $this->security->isGranted(RenditionVoter::READ, $rendition));
    }
}
