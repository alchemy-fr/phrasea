<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Collection;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class CollectionsRestoreHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ElasticSearchClient $elasticSearchClient,
        private AuditLogManager $auditLogManager,
        private SearchIndexer $searchIndexer,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CollectionsRestore $message): void
    {
        $entities = array_map(fn (string $id): Collection => $this->em->getRepository(Collection::class)->find($id), $message->getIds());
        $parentIds = array_values(array_unique(array_filter(array_map(fn (Collection $collection): ?string => $collection->getParent()?->getId(), $entities))));

        $this->em->createQueryBuilder()
            ->update(Collection::class, 'c')
            ->set('c.deletedAt', 'null')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $message->getIds())
            ->getQuery()
            ->execute();

        // A DQL update triggers no Doctrine listener
        foreach ($entities as $collection) {
            $this->auditLogManager->log(AuditLogActionEnum::ObjectRestored, $collection);
        }
        $this->em->flush();

        // A DQL update leaves the loaded entities untouched
        foreach ($entities as $collection) {
            $this->em->refresh($collection);
        }

        // A collection restored under a parent still in the trash stays deleted
        $restored = array_values(array_filter($entities, fn (Collection $collection): bool => !$collection->isDeleted()));
        if (empty($restored)) {
            return;
        }

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'terms' => [
                    'referenceCollectionPath' => array_map(fn (Collection $collection): string => $collection->getAbsolutePath(), $restored),
                ],
            ],
            [
                'source' => 'ctx._source.collectionDeleted=false',
                'lang' => 'painless',
            ]
        );

        $this->elasticSearchClient->updateByQuery(
            'collection',
            [
                'terms' => [
                    '_id' => array_map(fn (Collection $collection): string => $collection->getId(), $restored),
                ],
            ],
            [
                'source' => 'ctx._source.deleted=false',
                'lang' => 'painless',
            ]
        );

        if ([] !== $parentIds) {
            // Their "hasChildren" flag ignores the collections in the trash
            $this->searchIndexer->scheduleObjectsIndex(Collection::class, $parentIds, Operation::Upsert);
        }

        // Descendants with their own trash date, and the assets only linked to the branch
        foreach ($restored as $collection) {
            $this->bus->dispatch(new IndexCollectionBranch($collection->getId(), true));
        }
    }
}
