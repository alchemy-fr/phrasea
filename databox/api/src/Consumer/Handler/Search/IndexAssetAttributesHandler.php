<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Entity\Core\Attribute;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class IndexAssetAttributesHandler extends AbstractIndexIteratorHandler
{
    public function __invoke(IndexAssetAttributes $message): void
    {
        $attributes = $this->em->getRepository(Attribute::class)
            ->createQueryBuilder('a')
            ->select('a.id')
            ->distinct()
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $message->getAssetId())
            ->getQuery()
            ->toIterable();

        $this->indexObjects(Attribute::class, $attributes);
    }
}
