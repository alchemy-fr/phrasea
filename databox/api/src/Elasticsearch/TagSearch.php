<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\Filter\SearchQuery;
use App\Entity\Core\Tag;
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
        ?Operation $operation = null,
    ): Pagerfanta {
        $maxLimit = 50;
        $filterQuery = new Query\BoolQuery();
        $filterQuery->addFilter(new Query\Terms('workspaceId', $workspaceIds));

        // query (SuggestQueryFilter), workspace (ExactSearchFilter)...
        $searchQuery = new SearchQuery($filterQuery);
        $this->applyParameters($searchQuery, Tag::class, $operation, $options);

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($filterQuery);
        $query->setSort($searchQuery->hasSort() ? $searchQuery->getSort() : [
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
        self::applyPagination($data, $options, $maxLimit);
        $this->executeSearch($data->getCurrentPageResults(...));

        return $data;
    }
}
