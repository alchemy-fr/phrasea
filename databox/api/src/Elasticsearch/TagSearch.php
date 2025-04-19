<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TagSearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.tag')]
        private readonly PaginatedFinderInterface $finder,
    ) {
    }

    public function search(
        array $workspaceIds,
        array $options = [],
    ): Pagerfanta {
        $maxLimit = 50;
        $filterQuery = new Query\BoolQuery();
        $filterQuery->addFilter(new Query\Terms('workspaceId', $workspaceIds));

        $queryString = trim($options['query'] ?? '');
        if (!empty($queryString)) {
            $match = new Query\MultiMatch();
            $match->setQuery($queryString);
            $match->setType('bool_prefix');
            $match->setFields([
                'name.suggest',
                'name.suggest._2gram',
                'name.suggest._3gram',
            ]);
            $filterQuery->addMust($match);
        }

        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($filterQuery);
        $query->setSort([
            '_score' => 'DESC',
            'name.raw' => 'ASC',
        ]);
        $query->setHighlight([
            'pre_tags' => ['[hl]'],
            'post_tags' => ['[/hl]'],
            'fields' => [
                'name' => [
                    'fragment_size' => 255,
                    'number_of_fragments' => 1,
                ],
            ],
        ]);

        $data = $this->finder->findPaginated($query);
        $data->setMaxPerPage((int) $limit);
        $data->setCurrentPage((int) ($options['page'] ?? 1));

        return $data;
    }
}
