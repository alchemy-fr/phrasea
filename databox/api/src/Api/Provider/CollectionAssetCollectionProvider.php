<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Repository\Core\CollectionRepository;

final class CollectionAssetCollectionProvider extends AbstractCollectionAssetCollectionProvider
{
    public function __construct(
        private readonly CollectionRepository $collectionRepository,
    ) {
    }

    protected function resolveTarget(array $uriVariables): array
    {
        $collection = DoctrineUtil::findStrictByRepo($this->collectionRepository, $uriVariables['id'], throw404: true);

        // "collection" matches this collection only, unlike "parent" which spans the sub-tree
        return [$collection, ['collection' => $collection->getId()]];
    }
}
