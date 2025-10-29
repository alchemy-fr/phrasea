<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Consumer\Handler\Collection\CollectionsMoveToTrash;
use App\Doctrine\Delete\AssetDelete;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Asset;
use App\Entity\Core\CollectionAsset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class AssetsDeleteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private ElasticSearchClient $elasticSearchClient,
        private AssetDelete $assetDelete,
    ) {
    }

    public function __invoke(AssetsDelete $message): void
    {
        if (!empty($message->getCollections())) {
            $assetCollections = $this->em->getRepository(CollectionAsset::class)
                ->findBy(['asset' => $message->getIds(), 'collection' => $message->getCollections()]);
            foreach ($assetCollections as $assetCollection) {
                if ($assetCollection->getAsset()->getReferenceCollectionId() !== $assetCollection->getCollection()->getId()) {
                    $this->em->remove($assetCollection);
                }
            }
            $this->em->flush();

            return;
        }

        if ($message->isHardDelete()) {
            $this->assetDelete->deleteAssets($message->getIds());

            return;
        }

        /** @var Asset[] $assets */
        $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $message->getIds());
        DeferredIndexListener::disable();
        $collections = [];
        try {
            foreach ($assets as $asset) {
                if ($asset->getStoryCollection()) {
                    $collections[$asset->getStoryCollection()->getId()] = true;
                }
                $asset->setDeletedAt(new \DateTimeImmutable());
                $this->em->persist($asset);
            }
            $this->em->flush();
        } finally {
            DeferredIndexListener::enable();
        }

        if (!empty($collections)) {
            $this->bus->dispatch(new CollectionsMoveToTrash(array_keys($collections)));
        }

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'terms' => [
                    '_id' => $message->getIds(),
                ],
            ],
            [
                'source' => 'ctx._source.deleted=true',
                'lang' => 'painless',
            ]
        );
    }
}
