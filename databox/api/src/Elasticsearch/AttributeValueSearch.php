<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeInterface;
use App\Entity\Core\Attribute;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use Elastica\Aggregation;
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
 * (AssetSearch::createBaseFilterQueries()); the values of a definition that an asset policy
 * hides from the user on an asset do not count (see HiddenAttributeScope).
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
        private readonly AssetPolicyManager $assetPolicyManager,
    ) {
    }

    /**
     * The asset IDs of the page in order, the matching attribute IDs of each of them, and the
     * number of matching assets. An asset matches when it holds a matching value the user may
     * see; its attribute IDs are those of every matching value, hidden by an asset policy or not.
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

        $innerHits = new Query\InnerHits();
        $innerHits->setName(self::INNER_HITS_NAME);
        $innerHits->setSize(self::MAX_VALUES_PER_ASSET);
        $innerHits->setSource(false);
        $innerHits->setParam('docvalue_fields', [AttributeInterface::VALUES_FIELD.'.attributeId']);

        $nestedQuery = $this->createNestedQuery(self::createValueQuery($definitionIds, $values, $origin));
        $nestedQuery->setInnerHits($innerHits);

        $filterQuery = new Query\BoolQuery();
        foreach ($baseFilters as $filter) {
            $filterQuery->addFilter($filter);
        }
        if (null !== $assetId) {
            $filterQuery->addFilter(new Query\Terms('_id', [$assetId]));
        }

        $groups = HiddenAttributeScope::groupDefinitions($definitionIds, $this->assetPolicyManager->getHiddenAttributeScopes($userId, $groupIds));
        if (1 === count($groups) && empty($groups[0]['scopes'])) {
            $filterQuery->addFilter($nestedQuery);
        } else {
            $visibleValueQuery = new Query\BoolQuery();
            $visibleValueQuery->setMinimumShouldMatch(1);
            foreach ($groups as $group) {
                $groupQuery = HiddenAttributeScope::createExclusionQuery($group['scopes']) ?? new Query\BoolQuery();
                $groupQuery->addFilter($this->createNestedQuery(self::createValueQuery($group['definitionIds'], $values, $origin)));
                $visibleValueQuery->addShould($groupQuery);
            }
            $filterQuery->addFilter($visibleValueQuery);
            // Does not restrict the assets (the bool query has filters): only collects their matching values
            $filterQuery->addShould($nestedQuery);
        }

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
     * How many attribute values of the definitions hold each of the values (an entity ID), and
     * how many of them were set by a user, across the assets the user may see (quarantined ones
     * included when the user has access to them), leaving out the values of a definition that an
     * asset policy hides from the user on their asset.
     *
     * @param string[] $definitionIds
     * @param string[] $values
     *
     * @return array<string, array{attributes: int, humanAttributes: int}>
     */
    public function countUsage(?string $userId, array $groupIds, array $definitionIds, array $values): array
    {
        $values = array_values(array_unique($values));
        if (empty($definitionIds) || empty($values)) {
            return [];
        }

        try {
            $baseFilters = $this->assetSearch->createBaseFilterQueries($userId, $groupIds, filterStatus: false);
        } catch (NoWorkspaceAllowedException) {
            return [];
        }

        $filterQuery = new Query\BoolQuery();
        foreach ($baseFilters as $filter) {
            $filterQuery->addFilter($filter);
        }
        $filterQuery->addFilter($this->createNestedQuery(self::createValueQuery($definitionIds, $values)));

        $query = new Query($filterQuery);
        $query->setSize(0);
        $query->setTrackTotalHits(false);

        // One aggregation per group of definitions hidden on the same assets: a nested aggregation
        // cannot filter on the asset, the filter wraps it.
        $groups = HiddenAttributeScope::groupDefinitions($definitionIds, $this->assetPolicyManager->getHiddenAttributeScopes($userId, $groupIds));
        foreach ($groups as $i => $group) {
            $valueQuery = self::createValueQuery($group['definitionIds'], $values);
            $human = new Aggregation\Filter('human', new Query\Term([AttributeInterface::VALUES_FIELD.'.origin' => Attribute::ORIGIN_HUMAN]));
            $byValue = new Aggregation\Terms('values');
            $byValue->setField(AttributeInterface::VALUES_FIELD.'.value');
            $byValue->setSize(count($values));
            $byValue->addAggregation($human);
            // The other values of the matching assets are left out
            $matching = new Aggregation\Filter('matching', $valueQuery);
            $matching->addAggregation($byValue);
            $nested = new Aggregation\Nested('usage', AttributeInterface::VALUES_FIELD);
            $nested->addAggregation($matching);

            $visible = new Aggregation\Filter('group_'.$i, HiddenAttributeScope::createExclusionQuery($group['scopes']) ?? new Query\MatchAll());
            $visible->addAggregation($nested);
            $query->addAggregation($visible);
        }

        /** @var ResultSet $resultSet */
        $resultSet = $this->executeSearch(fn (): ResultSet => $this->index->search($query));

        $usage = [];
        foreach (array_keys($groups) as $i) {
            foreach ($resultSet->getAggregation('group_'.$i)['usage']['matching']['values']['buckets'] ?? [] as $bucket) {
                $key = (string) $bucket['key'];
                $usage[$key] ??= ['attributes' => 0, 'humanAttributes' => 0];
                $usage[$key]['attributes'] += (int) $bucket['doc_count'];
                $usage[$key]['humanAttributes'] += (int) ($bucket['human']['doc_count'] ?? 0);
            }
        }

        return $usage;
    }

    /**
     * @param string[] $definitionIds
     * @param string[] $values        exact values, any when empty
     */
    private static function createValueQuery(array $definitionIds, array $values = [], ?int $origin = null): Query\BoolQuery
    {
        $field = AttributeInterface::VALUES_FIELD;
        $valueQuery = new Query\BoolQuery();
        $valueQuery->addFilter(new Query\Terms($field.'.definitionId', array_values($definitionIds)));
        if (!empty($values)) {
            $valueQuery->addFilter(new Query\Terms($field.'.value', array_values($values)));
        }
        if (null !== $origin) {
            $valueQuery->addFilter(new Query\Term([$field.'.origin' => $origin]));
        }

        return $valueQuery;
    }

    private function createNestedQuery(Query\AbstractQuery $valueQuery): Query\Nested
    {
        $nestedQuery = new Query\Nested();
        $nestedQuery->setPath(AttributeInterface::VALUES_FIELD);
        $nestedQuery->setQuery($valueQuery);

        return $nestedQuery;
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
