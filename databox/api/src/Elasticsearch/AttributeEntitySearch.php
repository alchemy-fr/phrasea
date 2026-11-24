<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use ApiPlatform\Metadata\Operation;
use App\Attribute\Hierarchy\EntityHierarchyCriteria;
use App\Attribute\Hierarchy\EntityHierarchyCriteriaResolver;
use App\Elasticsearch\Filter\SearchQuery;
use App\Entity\Core\AttributeEntity;
use App\Security\AttributeEntityVisibility;
use App\Security\AttributeEntityVisibilityResolver;
use App\Security\Voter\AbstractVoter;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AttributeEntitySearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.attribute_entity')]
        private readonly PaginatedFinderInterface $finder,
        private readonly EntityHierarchyCriteriaResolver $criteriaResolver,
        private readonly AttributeEntityVisibilityResolver $visibilityResolver,
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
        if (null !== $criteria->minDepth || null !== $criteria->maxDepth) {
            $filterQuery->addFilter(new Query\Range('depth', array_filter([
                'gte' => $criteria->minDepth,
                'lte' => $criteria->maxDepth,
            ], fn (?int $v): bool => null !== $v)));
        }
        if (null !== $criteria->branchId) {
            $filterQuery->addFilter(new Query\Term(['ancestorIds' => $criteria->branchId]));
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
        $filterQuery->addFilter(self::createVisibilityQuery($this->visibilityResolver->resolve($operation)));

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

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(fn (AttributeEntity $entity): bool => $this->isGranted(AbstractVoter::READ, $entity), $adapter));
        self::applyPagination($result, $options, $maxLimit);
        $this->executeSearch($result->getCurrentPageResults(...));

        return $result;
    }

    /**
     * The Elasticsearch twin of the visibility rule applied by AttributeEntityExtension on the ORM listing.
     */
    private static function createVisibilityQuery(AttributeEntityVisibility $visibility): Query\AbstractQuery
    {
        if (null !== $visibility->listIds) {
            return new Query\Terms('listId', $visibility->listIds);
        }

        $visible = new Query\BoolQuery();
        $visible->setMinimumShouldMatch(1);
        $visible->addShould(new Query\Term(['status' => (string) AttributeEntity::STATUS_APPROVED]));
        if (null !== $visibility->userId) {
            $visible->addShould(new Query\Term(['creatorId' => $visibility->userId]));
        }
        if (!empty($visibility->pendingWorkspaceIds)) {
            $visible->addShould((new Query\BoolQuery())
                ->addFilter(new Query\Term(['status' => (string) AttributeEntity::STATUS_PENDING]))
                ->addFilter(new Query\Terms('workspaceId', $visibility->pendingWorkspaceIds)));
        }

        return $visible;
    }
}
