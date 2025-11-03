<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AssetDelete
{
    public function __construct(
        private EntityManagerInterface $em,
        private ElasticSearchClient $elasticSearchClient,
    ) {
    }

    public function deleteAssets(array $assetIds): void
    {
        $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $assetIds);
        DeferredIndexListener::disable();
        try {
            foreach ($assets as $asset) {
                $this->em->remove($asset);
            }
            $this->em->flush();
        } finally {
            DeferredIndexListener::enable();
        }

        $this->elasticSearchClient->deleteByQuery(
            'asset',
            [
                'terms' => [
                    '_id' => $assetIds,
                ],
            ],
        );
    }
}
