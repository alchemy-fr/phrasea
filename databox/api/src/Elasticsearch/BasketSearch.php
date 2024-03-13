<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Basket\Basket;
use App\Security\Voter\AbstractVoter;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BasketSearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.basket')]
        private readonly PaginatedFinderInterface $finder,
        private readonly QueryStringParser $queryStringParser,
    ) {
    }

    public function search(
        string $userId,
        array $groupIds,
        array $options = []
    ): Pagerfanta {
        $filterQueries = [];

        $aclBoolQuery = $this->createBasketACLBoolQuery($userId, $groupIds);
        if (null !== $aclBoolQuery) {
            $filterQueries[] = $aclBoolQuery;
        }

        $maxLimit = 50;
        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $rootQuery = new Query\BoolQuery();
        foreach ($filterQueries as $query) {
            $rootQuery->addFilter($query);
        }

        $queryString = trim($options['query'] ?? '');
        $parsed = $this->queryStringParser->parseQuery($queryString);

        if (!empty($parsed['should'])) {
            $rootQuery->addMust(new Query\MatchQuery('title', $parsed['should']));
        }
        foreach ($parsed['must'] as $must) {
            $multiMatch = new Query\MatchQuery('title', $must);
            $rootQuery->addMust($multiMatch);
        }

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($rootQuery);

        $query->setHighlight([
            'pre_tags' => ['[hl]'],
            'post_tags' => ['[/hl]'],
            'fields' => [
                'title' => [
                    'fragment_size' => 255,
                    'number_of_fragments' => 1,
                ],
            ],
        ]);

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(fn(Basket $basket
        ): bool => $this->isGranted(AbstractVoter::READ, $basket), $adapter));
        $result->setMaxPerPage((int)$limit);
        if ($options['page'] ?? false) {
            $result->setAllowOutOfRangePages(true);
            $result->setCurrentPage((int)$options['page']);
        }

        return $result;
    }

    private function createBasketACLBoolQuery(string $userId, array $groupIds): ?Query\BoolQuery
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $aclBoolQuery = new Query\BoolQuery();

        $aclBoolQuery->addShould(new Query\Term(['ownerId' => $userId]));
        $aclBoolQuery->addShould(new Query\Term(['users' => $userId]));
        if (!empty($groupIds)) {
            $aclBoolQuery->addShould(new Query\Terms('groups', $groupIds));
        }

        return $aclBoolQuery;
    }
}
