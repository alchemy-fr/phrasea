<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Entity\Core\Asset;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class IndexCollectionAssetsHandler extends AbstractIndexIteratorHandler
{
    public function __invoke(IndexCollectionAssets $message): void
    {
        $assets = $this->em->getRepository(Asset::class)
            ->createQueryBuilder('a')
            ->select('a.id')
            ->distinct()
            ->leftJoin('a.collections', 'ac')
            ->andWhere('a.referenceCollection = :collection OR ac.collection = :collection')
            ->setParameter('collection', $message->getCollectionId())
            ->getQuery()
            ->toIterable();

        $this->indexObjects(Asset::class, $assets, function (array $ids): void {
            foreach ($ids as $id) {
                $this->bus->dispatch(new IndexAssetAttributes($id));
            }
        });
    }
}
