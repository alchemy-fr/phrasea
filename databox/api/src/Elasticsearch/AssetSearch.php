<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\TagFilterManager;
use App\Security\Voter\AbstractVoter;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AssetSearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.asset')]
        private readonly PaginatedFinderInterface $finder,
        private readonly TagFilterManager $tagFilterManager,
        private readonly AttributeSearch $attributeSearch,
        private readonly QueryStringParser $queryStringParser,
        private readonly FacetHandler $facetHandler,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = [],
    ): array {
        $maxLimit = 50;

        $filterQueries = [];

        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);
        if (null !== $aclBoolQuery) {
            $filterQueries[] = $aclBoolQuery;
        }

        if (isset($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }
        if (isset($options['parents'])) {
            $parentCollections = $this->findCollections($options['parents']);
            $paths = array_map(fn (Collection $parentCollection): string => $parentCollection->getAbsolutePath(), $parentCollections);

            $filterQueries[] = new Query\Terms('collectionPaths', $paths);
        }

        if (isset($options['ids'])) {
            $filterQueries[] = new Query\Terms('_id', $options['ids']);
            $maxLimit = 500;
        }

        if (isset($options['workspaces'])) {
            $filterQueries[] = new Query\Terms('workspaceId', $options['workspaces']);
        }

        if (null !== $attrFilters = ($options['filters'] ?? null)) {
            if (is_string($attrFilters)) {
                $attrFilters = json_decode($attrFilters, true, 512, JSON_THROW_ON_ERROR);
            } else {
                $attrFilters = array_map(fn ($f): array => is_string($f) ? json_decode($f, true, 512, JSON_THROW_ON_ERROR) : $f, $attrFilters);
            }
            if (!empty($attrFilters)) {
                $filterQueries[] = $this->attributeSearch->addAttributeFilters($attrFilters);
            }
        }

        if (isset($options['tags_must']) || isset($options['tags_must_not'])) {
            $tagsBoolQuery = new Query\BoolQuery();
            $filterQueries[] = $tagsBoolQuery;

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

        $limit = $options['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $filterQuery = new Query\BoolQuery();
        foreach ($filterQueries as $query) {
            $filterQuery->addFilter($query);
        }

        if (null !== $tagQuery = $this->buildTagFilterQuery($userId, $groupIds)) {
            $filterQuery->addFilter($tagQuery);
        }

        $queryString = trim($options['query'] ?? '');
        $parsed = $this->queryStringParser->parseQuery($queryString);

        $attributeDefinitionGroups = $this->attributeSearch->buildSearchableAttributeDefinitionsGroups($userId, $groupIds);

        if (!empty($parsed['should'])) {
            $multiMatch = $this->attributeSearch->buildAttributeQuery($attributeDefinitionGroups, $parsed['should'], $options);
            $filterQuery->addMust($multiMatch);
        }
        foreach ($parsed['must'] as $must) {
            $multiMatch = $this->attributeSearch->buildAttributeQuery($attributeDefinitionGroups, $must, array_merge($options, [
                AttributeSearch::OPT_STRICT_PHRASE => true,
            ]));
            $filterQuery->addMust($multiMatch);
        }

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($filterQuery);

        $this->applySort($query, $options);

        $query->setHighlight([
            'pre_tags' => ['[hl]'],
            'post_tags' => ['[/hl]'],
            'fields' => [
                'title' => [
                    'fragment_size' => 255,
                    'number_of_fragments' => 1,
                ],
                AttributeInterface::ATTRIBUTES_FIELD.'.*' => [
                    'number_of_fragments' => 20,
                ],
            ],
        ]);

        $this->facetHandler->addBuiltInFacets($query);
        $this->attributeSearch->buildFacets($query, $userId, $groupIds, $options);

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(fn (Asset $asset): bool => $this->isGranted(AbstractVoter::READ, $asset), $adapter));
        $result->setMaxPerPage((int) $limit);
        if ($options['page'] ?? false) {
            $result->setAllowOutOfRangePages(true);
            $result->setCurrentPage((int) $options['page']);
        }
        $start = microtime(true);
        $result->getCurrentPageResults(); // Force query to ensure adapter will run it just once.
        $searchTime = microtime(true) - $start;

        $facets = $adapter->getAggregations();
        $facets = $this->facetHandler->normalizeBuckets($facets);

        $esQuery = $query->toArray();

        return [$result, $facets, $esQuery, $searchTime];
    }

    /**
     * @return Collection[]
     */
    private function findCollections(array $ids): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('t')
            ->from(Collection::class, 't')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    private function buildTagFilterQuery(?string $userId, array $groupIds): ?Query\BoolQuery
    {
        $ruleSet = $this->tagFilterManager->getUserRules($userId, $groupIds);

        $query = new Query\BoolQuery();
        $hasConditions = false;

        foreach ($ruleSet['workspaces'] as $wId => $rules) {
            if (empty($rules['include']) && empty($rules['exclude'])) {
                continue;
            }
            $workspace = $this->findWorkspace($wId);
            if ($workspace instanceof Workspace) {
                if (!empty($rules['include'])) {
                    $query->addMust($this->createIncludeQuery('workspaceId', $workspace->getId(), $rules['include']));
                    $hasConditions = true;
                }
                if (!empty($rules['exclude'])) {
                    $query->addMustNot($this->createExcludeQuery('workspaceId', $workspace->getId(), $rules['exclude']));
                    $hasConditions = true;
                }
            }
        }

        foreach ($ruleSet['collections'] as $cId => $rules) {
            if (empty($rules['include']) && empty($rules['exclude'])) {
                continue;
            }

            $collection = $this->findCollection($cId);
            if ($collection instanceof Collection) {
                $path = $collection->getAbsolutePath();
                if (!empty($rules['include'])) {
                    $query->addMust($this->createIncludeQuery('collectionPaths', $path, $rules['include']));
                    $hasConditions = true;
                }
                if (!empty($rules['exclude'])) {
                    $query->addMustNot($this->createExcludeQuery('collectionPaths', $path, $rules['exclude']));
                    $hasConditions = true;
                }
            }
        }

        if (!$hasConditions) {
            return null;
        }

        return $query;
    }

    private function applySort(Query $query, array $options): void
    {
        $sort = [];
        if (isset($options['order'])) {
            foreach ($options['order'] as $field => $way) {
                $esFieldInfo = $this->attributeSearch->getESFieldInfo($field);

                $w = strtoupper((string) $way);
                if (!in_array($w, ['ASC', 'DESC'], true)) {
                    throw new BadRequestHttpException(sprintf('Invalid sort way "%s"', $way));
                }

                $sort[] = [$esFieldInfo['name'] => $w];
            }
        } else {
            $sort[] = [
                '_score' => 'DESC',
                'createdAt' => 'DESC',
            ];
        }

        $sort[] = ['microseconds' => 'DESC'];
        $sort[] = ['sequence' => 'ASC'];

        $query->setSort($sort);
    }

    private function findWorkspace(string $id): ?Workspace
    {
        return $this->em->find(Workspace::class, $id);
    }

    private function createIncludeQuery(string $termCol, string $termValue, array $include): Query\BoolQuery
    {
        $query = new Query\BoolQuery();

        $notMatch = new Query\BoolQuery();
        $notMatch->addMustNot(new Query\Term([$termCol => $termValue]));
        $query->addShould($notMatch);

        $bool = new Query\BoolQuery();
        foreach ($include as $tag) {
            $bool->addFilter(new Query\Term(['tags' => $tag]));
        }
        $query->addShould($bool);

        return $query;
    }

    private function createExcludeQuery(string $termCol, string $termValue, array $exclude): Query\BoolQuery
    {
        $query = new Query\BoolQuery();
        $query->addFilter(new Query\Term([$termCol => $termValue]));
        $query->addFilter(new Query\Terms('tags', $exclude));

        return $query;
    }

    private function findCollection(string $id): ?Collection
    {
        return $this->em->find(Collection::class, $id);
    }
}
