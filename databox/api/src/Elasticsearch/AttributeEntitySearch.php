<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use ApiPlatform\Metadata\Operation;
use App\Attribute\Hierarchy\EntityHierarchyCriteria;
use App\Attribute\Hierarchy\EntityHierarchyCriteriaResolver;
use App\Elasticsearch\Filter\SearchQuery;
use App\Entity\Core\AttributeEntity;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AttributeEntitySearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.attribute_entity')]
        private readonly PaginatedFinderInterface $finder,
        private readonly EntityHierarchyCriteriaResolver $criteriaResolver,
    ) {
    }

    private function applyHierarchyCriteria(Query\BoolQuery $filterQuery, EntityHierarchyCriteria $criteria): void
    {
        if ($criteria->none) {
            $filterQuery->addMustNot(new Query\MatchAll());

            return;
        }
        if (null !== $criteria->listId) {
            $filterQuery->addFilter(new Query\Term(['listId' => $criteria->listId]));
        }
        if (null !== $criteria->parentId) {
            $filterQuery->addFilter(new Query\Term(['parentId' => $criteria->parentId]));
        }
        if (null !== $criteria->depth) {
            $filterQuery->addFilter(new Query\Term(['depth' => $criteria->depth]));
        }
        if (null !== $criteria->branchId) {
            $filterQuery->addFilter(new Query\Term(['ancestorIds' => $criteria->branchId]));
        }
        if (true === $criteria->unclassified) {
            $filterQuery->addMustNot(new Query\Exists('depth'));
        } elseif (false === $criteria->unclassified) {
            $filterQuery->addFilter(new Query\Exists('depth'));
        }
        if (true === $criteria->placeholder) {
            $filterQuery->addFilter(new Query\Term(['placeholder' => true]));
        } elseif (false === $criteria->placeholder) {
            // Documents indexed before the hierarchies have no "placeholder" field
            $filterQuery->addMustNot(new Query\Term(['placeholder' => true]));
        }
    }

    public function search(
        array $workspaceIds,
        array $options = [],
        ?Operation $operation = null,
    ): Pagerfanta {
        $maxLimit = 50;
        $filterQuery = new Query\BoolQuery();
        $filterQuery->addFilter(new Query\Terms('workspaceId', $workspaceIds));

        // query (SuggestQueryFilter), list/workspace (ExactSearchFilter), value (PartialSearchFilter), order[value] (SortFilter)
        $searchQuery = new SearchQuery($filterQuery);
        $this->applyParameters($searchQuery, AttributeEntity::class, $operation, $options);
        if (null !== $operation) {
            $this->applyHierarchyCriteria($filterQuery, $this->criteriaResolver->resolve($operation));
        }

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($filterQuery);
        $query->setSort($searchQuery->hasSort() ? $searchQuery->getSort() : [
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
        self::applyPagination($data, $options, $maxLimit);
        $this->executeSearch($data->getCurrentPageResults(...));

        return $data;
    }
}
