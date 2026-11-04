<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Elasticsearch\Filter\SearchQuery;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Template\AssetDataTemplate;
use App\Security\Voter\AbstractVoter;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AssetDataTemplateSearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.asset_data_template')]
        private readonly PaginatedFinderInterface $finder,
        private readonly EntityIriConverter $iriConverter,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $filters = [],
        ?Operation $operation = null,
    ): Pagerfanta {
        $filterQueries = [];

        $collection = self::firstScalar($filters['collection'] ?? null);
        if (null !== $collection) {
            $collection = $this->iriConverter->getItemFromIri(Collection::class, $collection);
        }

        $aclBoolQuery = $this->createTemplateACLBoolQuery($filters, $userId, $groupIds, $collection);
        $filterQueries[] = $aclBoolQuery;

        $queryString = trim((string) ($filters['query'] ?? ''));
        if (!empty($queryString)) {
            $queryBool = new Query\BoolQuery();
            $queryBool->addShould(new Query\MatchQuery('name', $queryString));
            $filterQueries[] = $queryBool;
        }

        $maxLimit = 50;

        $rootQuery = new Query\BoolQuery();
        foreach ($filterQueries as $query) {
            $rootQuery->addFilter($query);
        }

        // workspace (ExactSearchFilter)
        $searchQuery = new SearchQuery($rootQuery);
        $this->applyParameters($searchQuery, AssetDataTemplate::class, $operation, $filters);

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
        $query->setSort($searchQuery->hasSort() ? $searchQuery->getSort() : [
            'collectionDepth' => 'asc',
            '_score' => 'desc',
            'name.raw' => 'asc',
        ]);

        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $this->finder->findPaginated($query)->getAdapter();
        $result = new Pagerfanta(new FilteredPager(fn (AssetDataTemplate $template): bool => $this->security->isGranted(AbstractVoter::READ, $template), $adapter));
        self::applyPagination($result, $filters, $maxLimit);

        // Force query so a missing index surfaces here, not during serialization.
        $this->executeSearch($result->getCurrentPageResults(...));

        return $result;
    }

    /**
     * A parameter given once (`?x=a`) or as a list (`?x[]=a`): its first value.
     */
    private static function firstScalar(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = reset($value);
        }

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    private function createTemplateACLBoolQuery(array $filters, ?string $userId, array $groupIds, ?Collection $collection): Query\BoolQuery
    {
        $workspaceId = self::firstScalar($filters['workspace'] ?? null) ?? $collection?->getWorkspaceId();

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
