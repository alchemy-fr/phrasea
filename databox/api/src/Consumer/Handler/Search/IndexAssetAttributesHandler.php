<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\ESBundle\Indexer\EntityGroup;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class IndexAssetAttributesHandler extends AbstractIndexIteratorHandler
{
    public function __invoke(IndexAssetAttributes $message): void
    {
        $assetId = $message->getAssetId();
        $attributes = $this->em->getRepository(Attribute::class)
            ->createQueryBuilder('a')
            ->select('a.id')
            ->distinct()
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $assetId)
            ->getQuery()
            ->toIterable();

        $this->indexObjects(Attribute::class, $attributes, parents: [
            Asset::class => EntityGroup::fromArray([$assetId => 1]),
        ]);
    }
}
