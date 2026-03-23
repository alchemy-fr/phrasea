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
        $collections = array_map(function (string $id): string {
            $collection = $this->em->getRepository(Collection::class)->find($id);

            return $collection->getAbsolutePath();
        }, $message->getIds());

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
    }
}
