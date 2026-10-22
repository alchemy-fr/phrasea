<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeInterface;
use Elastica\Index;
use Elastica\Query;
use Elastica\Result;
use Elastica\ResultSet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The attribute values of some definitions across the assets the user can read, from the
 * "attributeValues" nested field of the asset documents (see AssetPostTransformListener).
 *
 * The assets are paginated (newest first) and filtered like any asset search
 * (AssetSearch::createBaseFilterQueries()): the total counts the readable assets only.
 */
class AttributeValueSearch extends AbstractSearch
{
    /**
     * Matching values returned per asset (bounded by the index.max_inner_result_window setting).
     */
    final public const int MAX_VALUES_PER_ASSET = 100;

    private const string INNER_HITS_NAME = 'values';

    public function __construct(
        #[Autowire(service: 'fos_elastica.index.asset')]
        private readonly Index $index,
        private readonly AssetSearch $assetSearch,
    ) {
    }

    /**
     * The asset IDs of the page in order, the matching attribute IDs of each of them,
     * and the number of matching assets.
     *
     * @param string[] $definitionIds
     * @param string[] $values        exact values, any when empty
     *
     * @return array{assetIds: string[], attributeIds: array<string, string[]>, total: int}
     */
    public function search(
        ?string $userId,
        array $groupIds,
        array $definitionIds,
        array $values = [],
        ?int $origin = null,
        ?string $assetId = null,
        int $page = 1,
        int $limit = 30,
    ): array {
        $empty = ['assetIds' => [], 'attributeIds' => [], 'total' => 0];
        if (empty($definitionIds)) {
            return $empty;
        }

        try {
            $baseFilters = $this->assetSearch->createBaseFilterQueries($userId, $groupIds);
        } catch (NoWorkspaceAllowedException) {
            return $empty;
        }

        $field = AttributeInterface::VALUES_FIELD;
        $valueQuery = new Query\BoolQuery();
        $valueQuery->addFilter(new Query\Terms($field.'.definitionId', array_values($definitionIds)));
        if (!empty($values)) {
            $valueQuery->addFilter(new Query\Terms($field.'.value', array_values($values)));
        }
        if (null !== $origin) {
            $valueQuery->addFilter(new Query\Term([$field.'.origin' => $origin]));
        }

        $innerHits = new Query\InnerHits();
        $innerHits->setName(self::INNER_HITS_NAME);
        $innerHits->setSize(self::MAX_VALUES_PER_ASSET);
        $innerHits->setSource(false);
        $innerHits->setParam('docvalue_fields', [$field.'.attributeId']);

        $nestedQuery = new Query\Nested();
        $nestedQuery->setPath($field);
        $nestedQuery->setQuery($valueQuery);
        $nestedQuery->setInnerHits($innerHits);

        $filterQuery = new Query\BoolQuery();
        foreach ($baseFilters as $filter) {
            $filterQuery->addFilter($filter);
        }
        if (null !== $assetId) {
            $filterQuery->addFilter(new Query\Terms('_id', [$assetId]));
        }
        $filterQuery->addFilter($nestedQuery);

        $query = new Query($filterQuery);
        $query->setSource(false);
        $query->setTrackTotalHits();
        // Same tiebreakers as the default asset search order: sorting on _id is disabled since Elasticsearch 8
        $query->setSort([
            ['createdAt' => 'DESC'],
            ['microseconds' => 'DESC'],
            ['sequence' => 'ASC'],
        ]);
        $query->setFrom((max(1, $page) - 1) * $limit);
        $query->setSize($limit);

        /** @var ResultSet $resultSet */
        $resultSet = $this->executeSearch(fn (): ResultSet => $this->index->search($query));

        $assetIds = [];
        $attributeIds = [];
        foreach ($resultSet->getResults() as $result) {
            $id = (string) $result->getId();
            $assetIds[] = $id;
            $attributeIds[$id] = self::getInnerAttributeIds($result);
        }

        return [
            'assetIds' => $assetIds,
            'attributeIds' => $attributeIds,
            'total' => $resultSet->getTotalHits(),
        ];
    }

    /**
     * @return string[]
     */
    private static function getInnerAttributeIds(Result $result): array
    {
        $ids = [];
        foreach ($result->getHit()['inner_hits'][self::INNER_HITS_NAME]['hits']['hits'] ?? [] as $innerHit) {
            foreach ($innerHit['fields'][AttributeInterface::VALUES_FIELD.'.attributeId'] ?? [] as $id) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }
}
