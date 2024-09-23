<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AttributeEntitySearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.attribute_entity')]
        private readonly PaginatedFinderInterface $finder,
    ) {
    }

    public function search(
        string $workspaceId,
        array $options = [],
    ): Pagerfanta {
        $maxLimit = 50;
        $filterQuery = new Query\BoolQuery();
        $filterQuery->addFilter(new Query\Term(['workspaceId' => $workspaceId]));

        $queryString = trim($options['query'] ?? '');
        if (!empty($queryString)) {
            $match = new Query\MultiMatch();
            $match->setQuery($queryString);
            $match->setType('bool_prefix');
            $match->setFields([
                'value.suggest',
                'value.suggest._2gram',
                'value.suggest._3gram',
            ]);
            $filterQuery->addMust($match);
        }

        $type = trim($options['type'] ?? '');
        if (!empty($type)) {
            $filterQuery->addFilter(new Query\Term(['type' => $type]));
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
            'value.raw' => 'ASC',
        ]);
        $query->setHighlight([
            'pre_tags' => ['[hl]'],
            'post_tags' => ['[/hl]'],
            'fields' => [
                'value' => [
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
