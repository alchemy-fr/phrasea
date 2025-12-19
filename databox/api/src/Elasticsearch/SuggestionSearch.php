<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Repository\Core\AttributeDefinitionRepository;
use Elastica\Collapse;
use Elastica\Query;
use Elastica\Result;
use FOS\ElasticaBundle\Elastica\Index;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SuggestionSearch extends AbstractSearch
{
    private const string SUGGEST_FIELD = 'suggestion';
    private const string SUGGEST_SUB_FIELD = 'suggest';
    private const string DEFINITION_ID_FIELD = 'definitionId';

    public function __construct(
        #[Autowire(service: 'fos_elastica.index.collection')]
        private readonly Index $collectionIndex,
        #[Autowire(service: 'fos_elastica.index.asset')]
        private readonly Index $assetIndex,
        #[Autowire(service: 'fos_elastica.index.attribute')]
        private readonly Index $attributeIndex,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly string $kernelEnv,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = [],
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

        $suggestAttributes = $this->attributeDefinitionRepository
            ->getSearchableAttributes($userId, $groupIds, [
                AttributeDefinitionRepository::OPT_SUGGEST_ENABLED => true,
            ]);

        $definitionNames = [];
        foreach ($suggestAttributes as $definition) {
            $definitionNames[$definition->getId()] = $definition->getName();
        }

        $match = new Query\MatchQuery(self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD, $queryString);
        $filterQuery->addMust($match);
        $filterType = new Query\BoolQuery();
        $filterType->addShould(new Query\Terms('definitionId', array_keys($definitionNames)));
        $filterType->addShould(new Query\Terms('_index', [
            $this->collectionIndex->getName(),
            $this->assetIndex->getName(),
        ]));
        $filterQuery->addFilter($filterType);

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
                self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD => new \stdClass(),
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
            $this->attributeIndex->getName() => 1.2,
            $this->assetIndex->getName() => 1.1,
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
