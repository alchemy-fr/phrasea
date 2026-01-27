<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Api\EntityIriConverter;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Template\AssetDataTemplate;
use App\Security\Voter\AbstractVoter;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AssetDataTemplateSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.asset_data_template')]
        private PaginatedFinderInterface $finder,
        private Security $security,
        private EntityIriConverter $iriConverter,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $filters = [],
    ): Pagerfanta {
        $filterQueries = [];

        $collection = $filters['collection'] ?? null;
        if (null !== $collection) {
            $collection = $this->iriConverter->getItemFromIri(Collection::class, $collection);
        }

        $aclBoolQuery = $this->createACLBoolQuery($filters, $userId, $groupIds, $collection);
        if (null !== $aclBoolQuery) {
            $filterQueries[] = $aclBoolQuery;
        }

        $queryString = trim($filters['query'] ?? '');
        if (!empty($queryString)) {
            $queryBool = new Query\BoolQuery();
            $queryBool->addShould(new Query\MatchQuery('name', $queryString));
            $filterQueries[] = $queryBool;
        }

        $maxLimit = 50;
        $limit = $filters['limit'] ?? $maxLimit;
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        $rootQuery = new Query\BoolQuery();
        foreach ($filterQueries as $query) {
            $rootQuery->addFilter($query);
        }

        if ($collection instanceof Collection) {
            $collectionQuery = new Query\BoolQuery();

            $strict = new Query\BoolQuery();
            $strict->addMust(new Query\Term(['collectionId' => $collection->getId()]));

            $nonStrict = new Query\BoolQuery();
            $nonStrict->addMust(new Query\Terms('collectionId', array_values(array_filter(explode('/', $collection->getAbsolutePath())))));
            $nonStrict->addMust(new Query\Term(['includeCollectionChildren' => true]));

            $wsNonStrict = new Query\BoolQuery();
            $wsNonStrict->addMust(new Query\Term(['collectionDepth' => 0]));
            $wsNonStrict->addMust(new Query\Term(['includeCollectionChildren' => true]));

            $collectionQuery->addShould($strict);
            $collectionQuery->addShould($nonStrict);
            $collectionQuery->addShould($wsNonStrict);

            $rootQuery->addMust($collectionQuery);
        } else {
            $rootQuery->addMust(new Query\Term(['collectionDepth' => 0]));
        }

        $query = new Query();
        $query->setTrackTotalHits();
        $query->setQuery($rootQuery);
        $query->setSort([
            'collectionDepth' => 'asc',
            '_score' => 'desc',
            'name.raw' => 'asc',
        ]);

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(fn (AssetDataTemplate $template): bool => $this->security->isGranted(AbstractVoter::READ, $template), $adapter));
        $result->setMaxPerPage((int) $limit);
        if ($filters['page'] ?? false) {
            $result->setCurrentPage((int) $filters['page']);
        }

        return $result;
    }

    private function createACLBoolQuery(array $filters, ?string $userId, array $groupIds, ?Collection $collection): ?Query\BoolQuery
    {
        $workspaceId = $filters['workspace'] ?? $collection?->getWorkspaceId() ?? null;

        if (empty($workspaceId)) {
            throw new BadRequestHttpException('"workspace" filter is mandatory');
        }
        /** @var Workspace $workspace */
        $workspace = $this->iriConverter->getItemFromIri(Workspace::class, $workspaceId);

        if (null !== $collection && $collection->getWorkspaceId() !== $workspace->getId()) {
            throw new BadRequestHttpException('Collection is not in the same workspace');
        }

        $aclBoolQuery = new Query\BoolQuery();

        if (null !== $collection) {
            if (!$this->security->isGranted(AbstractVoter::EDIT, $collection)) {
                $aclBoolQuery->addMust(new Query\Term(['collectionId' => 'NONE']));
            }
        } elseif (!$this->security->isGranted(AbstractVoter::READ, $workspace)) {
            $aclBoolQuery->addMust(new Query\Term(['workspaceId' => 'NONE']));
        }

        $rootQuery = new Query\BoolQuery();
        $rootQuery->addMust(new Query\Term(['workspaceId' => $workspace->getId()]));

        $rootQuery->addMust($aclBoolQuery);
        $shoulds = [];

        $shoulds[] = new Query\Term(['public' => true]);
        if (null !== $userId) {
            $shoulds[] = new Query\Term(['ownerId' => $userId]);
            $shoulds[] = new Query\Term(['users' => $userId]);
            $shoulds[] = new Query\Terms('groups', $groupIds);
        }

        foreach ($shoulds as $query) {
            $aclBoolQuery->addShould($query);
        }

        return $rootQuery;
    }
}
