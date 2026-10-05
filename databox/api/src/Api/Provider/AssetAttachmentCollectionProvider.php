<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AssetAttachment;

/**
 * Attachments of one asset (`?assetId=`), which must be readable.
 */
final class AssetAttachmentCollectionProvider extends AbstractAssetFilteredCollectionProvider
{
    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $asset = $this->getAsset($context);

        return $this->em->getRepository(AssetAttachment::class)
            ->createQueryBuilder('t')
            ->andWhere('t.asset = :a')
            ->setParameter('a', $asset->getId())
            ->addOrderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
