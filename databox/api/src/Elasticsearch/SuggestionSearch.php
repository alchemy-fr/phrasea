<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use Elastica\Collapse;
use Elastica\Query;
use Elastica\Result;
use FOS\ElasticaBundle\Elastica\Index;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use stdClass;

class SuggestionSearch extends AbstractSearch
{
    private const SUGGEST_FIELD = 'suggestion';
    private const SUGGEST_SUB_FIELD = 'suggest';
    private const DEFINITION_ID_FIELD = 'definitionId';

    public function __construct(
        private readonly Index $collectionIndex,
        private readonly Index $assetIndex,
        private readonly Index $attributeIndex,
        private readonly string $kernelEnv,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        $filterQuery = new Query\BoolQuery();

        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);
        if (null !== $aclBoolQuery) {
            $filterQuery->addFilter($aclBoolQuery);
        }

        if (isset($options['workspaces'])) {
            $filterQuery->addFilter(new Query\Terms('workspaceId', $options['workspaces']));
        }

        $queryString = trim($options['query'] ?? '');
        $queryString = preg_replace('#^"(.*)$#', '$1', $queryString);
        $queryString = preg_replace('#(.*)"$#', '$1', $queryString);

        /** @var AttributeDefinition[] $suggestAttributes */
        $suggestAttributes = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes($userId, $groupIds, [
                AttributeDefinitionRepositoryInterface::OPT_SUGGEST_ENABLED => true,
            ]);

        $definitionNames = [];
        foreach ($suggestAttributes as $definition) {
            $definitionNames[$definition->getId()] = $definition->getName();
        }

        $match = new Query\MatchQuery('suggestion.suggest', $queryString);
        $filterQuery->addMust($match);
        $filterType = new Query\BoolQuery();
        $filterType->addShould(new Query\Terms('definitionId', array_keys($definitionNames)));
        $filterType->addShould(new Query\Terms('_index', [
            $this->collectionIndex->getName(),
            $this->assetIndex->getName(),
        ]));
        $filterQuery->addMust($filterType);

        $query = new Query();
        $query->setTrackTotalHits(false);
        $query->setQuery($filterQuery);

        $query->setSort([
            '_score' => 'DESC',
            'createdAt' => 'DESC',
        ]);

        $query->setSize(15);

        $query->setHighlight([
            'pre_tags' => ['[hl]'],
            'post_tags' => ['[/hl]'],
            'fields' => [
                self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD => new stdClass(),
            ],
        ]);
        $collapse = new Collapse();
        $collapse->setFieldname(self::SUGGEST_FIELD.'.raw');
        $query->setCollapse($collapse);
        $query->setSource([
            'includes' => [
                self::DEFINITION_ID_FIELD,
            ],
        ]);
        $query->setIndicesBoost([
            $this->attributeIndex->getName() => 100,
            $this->assetIndex->getName() => 2,
        ]);

        $start = microtime(true);

        $search = $this->collectionIndex->createSearch($query);
        $search->addIndex($this->assetIndex);
        $search->addIndex($this->attributeIndex);
        $result = $search->search();

        $searchTime = microtime(true) - $start;

        $indexTitles = [
            'asset_'.$this->kernelEnv => 'Asset',
            'collection_'.$this->kernelEnv => 'Collection',
        ];

        $result = new Pagerfanta(new ArrayAdapter(array_map(function (Result $result) use (
            $indexTitles,
            $definitionNames,
        ): array {
            $hl = $result->getHighlights()[self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD];
            $indexName = preg_replace('#_\d{4}-\d{2}-\d{2}-\d{6}$#', '', $result->getIndex());

            if ('attribute_'.$this->kernelEnv === $indexName) {
                $type = $definitionNames[$result->getSource()['definitionId']];
            } else {
                $type = $indexTitles[$indexName];
            }

            return [
                'id' => $result->getId(),
                'name' => preg_replace('#\[/?hl]#', '', $hl),
                'hl' => $hl,
                't' => $type,
            ];
        }, $result->getResults())));
        $esQuery = $query->toArray();

        return [$result, $esQuery, $searchTime];
    }
}
