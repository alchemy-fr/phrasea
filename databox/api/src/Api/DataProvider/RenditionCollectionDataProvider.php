<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use App\Entity\Core\AssetRendition;
use App\Security\Voter\RenditionVoter;

class RenditionCollectionDataProvider extends AbstractAssetFilteredCollectionDataProvider
{
    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
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

        return array_filter($renditions, function (AssetRendition $rendition): bool {
            return $this->security->isGranted(RenditionVoter::READ, $rendition);
        });
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return AssetRendition::class === $resourceClass;
    }
}
