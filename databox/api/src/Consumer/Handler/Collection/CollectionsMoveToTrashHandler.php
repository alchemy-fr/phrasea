<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Collection;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CollectionsMoveToTrashHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ElasticSearchClient $elasticSearchClient,
        private AuditLogManager $auditLogManager,
        private SearchIndexer $searchIndexer,
    ) {
    }

    public function __invoke(CollectionsMoveToTrash $message): void
    {
        $entities = array_map(fn (string $id): Collection => $this->em->getRepository(Collection::class)->find($id), $message->getIds());
        $collections = array_map(fn (Collection $collection): string => $collection->getAbsolutePath(), $entities);
        $parentIds = array_values(array_unique(array_filter(array_map(fn (Collection $collection): ?string => $collection->getParent()?->getId(), $entities))));

        $this->em->createQueryBuilder()
            ->update(Collection::class, 'c')
            ->set('c.deletedAt', ':deletedAt')
            ->where('c.id IN (:ids)')
            ->setParameter('deletedAt', new \DateTimeImmutable())
            ->setParameter('ids', $message->getIds())
            ->getQuery()
            ->execute();

        // A DQL update triggers no Doctrine listener
        foreach ($entities as $collection) {
            $this->auditLogManager->log(AuditLogActionEnum::ObjectTrashed, $collection);
        }
        $this->em->flush();

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'terms' => [
                    'referenceCollectionPath' => $collections,
                ],
            ],
            [
                'source' => 'ctx._source.collectionDeleted=true',
                'lang' => 'painless',
            ]
        );

        $this->elasticSearchClient->updateByQuery(
            'collection',
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

        if ([] !== $parentIds) {
            // Their "hasChildren" flag ignores the collections in the trash. A DQL update leaves
            // the loaded entities as they were: the parents are indexed from the current state.
            foreach ($entities as $collection) {
                $this->em->refresh($collection);
            }
            $this->searchIndexer->scheduleObjectsIndex(Collection::class, $parentIds, Operation::Upsert);
        }
    }
}
