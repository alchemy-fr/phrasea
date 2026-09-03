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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BasketSearch extends AbstractSearch
{
    /**
     * Most recently created baskets first.
     */
    final public const string ORDER_CREATED_AT = 'createdAt';

    final public const array ORDERS = [
        self::ORDER_CREATED_AT,
    ];

    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.basket')]
        private readonly PaginatedFinderInterface $finder,
        private readonly QueryStringParser $queryStringParser,
    ) {
    }

    public function search(
        string $userId,
        array $groupIds,
        array $options = [],
    ): Pagerfanta {
        $filterQueries = [];

        $aclBoolQuery = $this->createBasketACLBoolQuery($userId, $groupIds);
        if (null !== $aclBoolQuery) {
            $filterQueries[] = $aclBoolQuery;
        }

        $isArchived = filter_var($options['archived'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        if (filter_var($options['includeArchived'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false) {
            $isArchived = null;
        }

        if (null !== $isArchived) {
            $filterQueries[] = new Query\Term(['isArchived' => $isArchived]);
        }

        $maxLimit = 30;
        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $rootQuery = new Query\BoolQuery();
        foreach ($filterQueries as $query) {
            $rootQuery->addFilter($query);
        }

        $queryString = trim($options['query'] ?? '');

        if (!empty($queryString)) {
            $searchQuery = new Query\BoolQuery();
            $parsed = $this->queryStringParser->parseQuery($queryString);

            if (!empty($parsed['should'])) {
                $searchQuery->setMinimumShouldMatch(1);
                $multiMatch = new Query\MultiMatch();
                $multiMatch->setFields(['name', 'description']);
                $multiMatch->setQuery($parsed['should']);
                $searchQuery->addShould($multiMatch);
            }
            foreach ($parsed['must'] as $must) {
                $multiMatch = new Query\MultiMatch();
                $multiMatch->setFields(['name', 'description']);
                $multiMatch->setQuery($must);
                $searchQuery->addMust($multiMatch);
            }

            $rootQuery->addMust($searchQuery);
        }

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($rootQuery);
        $this->applySort($query, $options);

        $query->setHighlight([
            'pre_tags' => ['[hl]'],
            'post_tags' => ['[/hl]'],
            'fields' => [
                'name' => [
                    'fragment_size' => 255,
                    'number_of_fragments' => 1,
                ],
                'description' => [
                    'fragment_size' => 255,
                    'number_of_fragments' => 1,
                ],
            ],
        ]);

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(fn (Basket $basket): bool => $this->isGranted(AbstractVoter::READ, $basket), $adapter));
        $result->setMaxPerPage((int) $limit);
        if ($options['page'] ?? false) {
            $result->setAllowOutOfRangePages(true);
            $result->setCurrentPage((int) $options['page']);
        }
        $result->getCurrentPageResults();

        return $result;
    }

    private function applySort(Query $query, array $options): void
    {
        $order = trim((string) ($options['order'] ?? ''));
        if ('' === $order) {
            return;
        }

        if (!in_array($order, self::ORDERS, true)) {
            throw new BadRequestHttpException(sprintf('Invalid order "%s". Supported values are: "%s"', $order, implode('", "', self::ORDERS)));
        }

        $query->setSort([
            [
                'createdAt' => [
                    'order' => 'desc',
                    'missing' => '_last',
                ],
            ],
            [
                '_id' => [
                    'order' => 'asc',
                ],
            ],
        ]);
    }

    private function createBasketACLBoolQuery(string $userId, array $groupIds): ?Query\BoolQuery
    {
        if ($this->isAdmin()) {
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
