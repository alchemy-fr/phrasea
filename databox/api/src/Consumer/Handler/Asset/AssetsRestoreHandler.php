<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Consumer\Handler\Collection\CollectionsRestore;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class AssetsRestoreHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private ElasticSearchClient $elasticSearchClient,
    ) {
    }

    public function __invoke(AssetsRestore $message): void
    {
        /** @var Asset[] $assets */
        $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $message->getIds());

        $collections = [];
        DeferredIndexListener::disable();
        try {
            foreach ($assets as $asset) {
                if ($asset->getStoryCollection()) {
                    $collections[$asset->getStoryCollection()->getId()] = true;
                }
                $asset->setDeletedAt(null);
                $this->em->persist($asset);
            }
            $this->em->flush();
        } finally {
            DeferredIndexListener::enable();
        }

        if (!empty($collections)) {
            $this->bus->dispatch(new CollectionsRestore(array_keys($collections)));
        }

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'terms' => [
                    '_id' => $message->getIds(),
                ],
            ],
            [
                'source' => 'ctx._source.deleted=false',
                'lang' => 'painless',
            ]
        );

    }
}
