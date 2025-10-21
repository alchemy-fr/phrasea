<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Collection;

use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CollectionsRestoreHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ElasticSearchClient $elasticSearchClient,
    ) {
    }

    public function __invoke(CollectionsRestore $message): void
    {
        $this->em->createQueryBuilder()
            ->update(Collection::class, 'c')
            ->set('c.deletedAt', 'null')
            ->where('c.id IN (:ids)')
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
                'source' => 'ctx._source.collectionDeleted=false',
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
                'source' => 'ctx._source.deleted=false',
                'lang' => 'painless',
            ]
        );
    }
}
