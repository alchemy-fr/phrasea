<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;

class AssetSearch
{
    private PaginatedFinderInterface $finder;

    public function __construct(PaginatedFinderInterface $finder)
    {
        $this->finder = $finder;
    }

    public function search(
        ?string $queryString,
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        $mustQueries = [];

        $aclBoolQuery = new Query\BoolQuery();
        $mustQueries[] = $aclBoolQuery;

        $shoulds = [
            new Query\Term(['public' => true]),
        ];

        if (null !== $userId) {
            $shoulds[] = new Query\Term(['ownerId' => $userId]);
            $shoulds[] = new Query\Term(['users' => $userId]);
            $shoulds[] = new Query\Terms('groups', $groupIds);
        }

        foreach ($shoulds as $query) {
            $aclBoolQuery->addShould($query);
        }

        if (isset($options['tags_must']) || isset($options['tags_must_not'])) {
            $tagsBoolQuery = new Query\BoolQuery();
            $mustQueries[] = $tagsBoolQuery;

            if (isset($options['tags_must'])) {
                foreach ($options['tags_must'] as $tag) {
                    $tagsBoolQuery->addMust(new Query\Term(['tags' => $tag]));
                }
            }

            if (isset($options['tags_must_not'])) {
                foreach ($options['tags_must_not'] as $tag) {
                    $tagsBoolQuery->addMustNot(new Query\Term(['tags' => $tag]));
                }
            }
        }

        $maxLimit = 100;
        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $filterQuery = new Query\BoolQuery();
        foreach ($mustQueries as $query) {
            $filterQuery->addFilter($query);
        }

//        dump($filterQuery->toArray());

        $data = $this->finder->find($filterQuery, $limit);

        return $data;
    }
}
