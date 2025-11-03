<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CollectionsMoveToTrashHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ElasticSearchClient $elasticSearchClient,
    ) {
    }

    public function __invoke(CollectionsMoveToTrash $message): void
    {
        $this->em->createQueryBuilder()
            ->update(Collection::class, 'c')
            ->set('c.deletedAt', ':deletedAt')
            ->where('c.id IN (:ids)')
            ->setParameter('deletedAt', new \DateTimeImmutable())
            ->setParameter('ids', $message->getIds())
            ->getQuery()
            ->execute();

        $this->elasticSearchClient->updateByQuery(
            'asset',
            [
                'terms' => [
                    'referenceCollectionId' => $message->getIds(),
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
    }
}
