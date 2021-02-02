<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\TagFilterManager;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;

class CollectionSearch extends AbstractSearch
{
    private PaginatedFinderInterface $finder;
    private EntityManagerInterface $em;

    public function __construct(
        PaginatedFinderInterface $finder,
        EntityManagerInterface $em
    ) {
        $this->finder = $finder;
        $this->em = $em;
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        $mustQueries = [];

        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);
        $mustQueries[] = $aclBoolQuery;

        $maxLimit = 100;
        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $filterQuery = new Query\BoolQuery();
        foreach ($mustQueries as $query) {
            $filterQuery->addFilter($query);
        }

        if (isset($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }
        if (isset($options['parents'])) {
            $parentCollections = $this->findCollections($options['parents']);
            $parentsBoolQuery = new Query\BoolQuery();
            array_map(function (Collection $parentCollection) use ($parentsBoolQuery): void {
                $q = new Query\BoolQuery();
                $q->addFilter(new Query\Term(['absolutePath' => $parentCollection->getAbsolutePath()]));
                $q->addFilter(new Query\Term(['pathDepth' => $parentCollection->getPathDepth() + 1]));

                $parentsBoolQuery->addShould($q);
            }, $parentCollections);

            $filterQuery->addFilter($parentsBoolQuery);
        } else {
            $filterQuery->addFilter(new Query\Term(['pathDepth' => 0]));
        }

        if (isset($options['workspaces'])) {
            $filterQuery->addFilter(
                new Query\Terms('workspaceId', $options['workspaces'])
            );
        }

//        dump($filterQuery->toArray());

        $data = $this->finder->find($filterQuery, $limit);

        return $data;
    }

    /**
     * @return Collection[]
     */
    private function findCollections(array $ids): array
    {
        return $this->findEntityByIds(Collection::class, $ids);
    }

    private function findCollection(string $id): Collection
    {
        return $this->em->find(Collection::class, $id);
    }

    /**
     * @return Workspace[]
     */
    private function findWorkspaces(array $ids): array
    {
        return $this->findEntityByIds(Workspace::class, $ids);
    }

    private function findEntityByIds(string $entityName, array $ids): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('t')
            ->from($entityName, 't')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
