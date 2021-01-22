<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\TagFilterManager;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;

class AssetSearch extends AbstractSearch
{
    private PaginatedFinderInterface $finder;
    private TagFilterManager $tagFilterManager;
    private EntityManagerInterface $em;

    public function __construct(
        PaginatedFinderInterface $finder,
        TagFilterManager $tagFilterManager,
        EntityManagerInterface $em
    ) {
        $this->finder = $finder;
        $this->tagFilterManager = $tagFilterManager;
        $this->em = $em;
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        $mustQueries = [];

        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);
        $mustQueries[] = $aclBoolQuery;

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

        $filterQuery->addFilter($this->buildTagFilterQuery($userId, $groupIds));

//        dump($filterQuery->toArray());

        $data = $this->finder->find($filterQuery, $limit);

        return $data;
    }

    private function buildTagFilterQuery(?string $userId, array $groupIds): Query\BoolQuery
    {
        $ruleSet = $this->tagFilterManager->getUserRules($userId, $groupIds);

        $query = new Query\BoolQuery();

        foreach ($ruleSet['workspaces'] as $wId => $rules) {
            if (empty($rules['include']) && empty($rules['exclude'])) {
                continue;
            }
            $workspace = $this->findWorkspace($wId);
            if ($workspace instanceof Workspace) {
                if (!empty($rules['include'])) {
                    $query->addMust($this->createIncludeQuery('workspaceId', $workspace->getId(), $rules['include']));
                }
                if (!empty($rules['exclude'])) {
                    $query->addMustNot($this->createExcludeQuery('workspaceId', $workspace->getId(), $rules['exclude']));
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
                }
                if (!empty($rules['exclude'])) {
                    $query->addMustNot($this->createExcludeQuery('collectionPaths', $path, $rules['exclude']));
                }
            }
        }

        return $query;
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

    private function findWorkspace(string $id): ?Workspace
    {
        return $this->em->find(Workspace::class, $id);
    }
}
