<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\TagFilterManager;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Security;

class AssetSearch extends AbstractSearch
{
    private PaginatedFinderInterface $finder;
    private TagFilterManager $tagFilterManager;
    private EntityManagerInterface $em;
    private AttributeSearch $attributeSearch;
    private QueryStringParser $queryStringParser;
    private FacetHandler $facetHandler;

    public function __construct(
        PaginatedFinderInterface $finder,
        TagFilterManager $tagFilterManager,
        EntityManagerInterface $em,
        AttributeSearch $attributeSearch,
        Security $security,
        QueryStringParser $queryStringParser,
        FacetHandler $facetHandler
    ) {
        $this->finder = $finder;
        $this->tagFilterManager = $tagFilterManager;
        $this->em = $em;
        $this->attributeSearch = $attributeSearch;
        $this->security = $security;
        $this->queryStringParser = $queryStringParser;
        $this->facetHandler = $facetHandler;
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        $filterQueries = [];

        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);
        $filterQueries[] = $aclBoolQuery;

        if (isset($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }
        if (isset($options['parents'])) {
            $parentCollections = $this->findCollections($options['parents']);
            $paths = array_map(function (Collection $parentCollection): string {
                return $parentCollection->getAbsolutePath();
            }, $parentCollections);

            $filterQueries[] = new Query\Terms('collectionPaths', $paths);
        }

        if (isset($options['workspaces'])) {
            $filterQueries[] = new Query\Terms('workspaceId', $options['workspaces']);
        }

        if (null !== $attrFilters = ($options['filters'] ?? null)) {
            if (is_string($attrFilters)) {
                $attrFilters = \GuzzleHttp\json_decode($attrFilters, true);
            } else {
                $attrFilters = array_map(function ($f): array {
                    return is_string($f) ? \GuzzleHttp\json_decode($f, true) : $f;
                }, $attrFilters);
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

        $maxLimit = 30;
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

        if (!empty($parsed['should'])) {
            $multiMatch = $this->attributeSearch->buildAttributeQuery($parsed['should'], $userId, $groupIds, $options);
            $filterQuery->addMust($multiMatch);
        }
        foreach ($parsed['must'] as $must) {
            $multiMatch = $this->attributeSearch->buildAttributeQuery($must, $userId, $groupIds, array_merge($options, [
                AttributeSearch::OPT_STRICT_PHRASE => true,
            ]));
            $filterQuery->addMust($multiMatch);
        }

        $query = new Query();
        $query->setTrackTotalHits(true);
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
                'attributes.*' => [
                    'number_of_fragments' => 20,
                ],
            ],
        ]);

        $this->facetHandler->buildWorkspaceFacet($query);
        $this->facetHandler->buildCollectionFacet($query);
        $this->facetHandler->buildPrivacyFacet($query);
        $this->facetHandler->buildTagFacet($query);
        $this->facetHandler->buildDateFacet($query, 'createdAt', 'Creation date');
        $this->attributeSearch->buildFacets($query, $userId, $groupIds, $options);

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(function (Asset $asset): bool {
            return $this->security->isGranted(AssetVoter::READ, $asset);
        }, $adapter));
        $result->setMaxPerPage($limit);
        if ($options['page'] ?? false) {
            $result->setCurrentPage($options['page']);
        }

        $start = microtime(true);
        $facets = $adapter->getAggregations();

        $facets = $this->facetHandler->normalizeBuckets($facets);

        $searchTime = microtime(true) - $start;

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
        $sort = [
            '_score',
        ];
        if (isset($options['order'])) {
            foreach ($options['order'] as $field => $way) {
                $esField = $this->attributeSearch->getESFieldName($field);

                $w = strtoupper($way);
                if (!in_array($w, ['ASC', 'DESC'], true)) {
                    throw new BadRequestHttpException(sprintf('Invalid sort way "%s"', $way));
                }

                $sort[] = [$esField => $w];
            }
        } else {
            $sort[] = ['createdAt' => 'DESC'];
        }

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
