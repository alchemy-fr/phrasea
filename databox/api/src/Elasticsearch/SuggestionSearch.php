<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use Elastica\Query;
use Elastica\Result;
use FOS\ElasticaBundle\Elastica\Index;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;

class SuggestionSearch extends AbstractSearch
{
    private const SUGGEST_FIELD = 'title';
    final public const SUGGEST_SUB_FIELD = 'suggest';

    public function __construct(
        private readonly Index $collectionIndex,
        private readonly Index $assetIndex,
        private readonly string $kernelEnv,
        private readonly FieldNameResolver $fieldNameResolver,
        private readonly AttributeTypeRegistry $typeRegistry,
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

        $multiMatch = new Query\MultiMatch();
        $multiMatch->setType(Query\MultiMatch::TYPE_BEST_FIELDS);
        $multiMatch->setQuery($queryString);
        $fields = [];
        $addField = function (string $f) use (&$fields, $queryString): void {
            $fields[] = $f.'.'.self::SUGGEST_SUB_FIELD;
        };

        $addField(self::SUGGEST_FIELD);

        $language = $options['locale'] ?? '*';

        foreach ($suggestAttributes as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldNameFromDefinition($definition);
            $type = $this->typeRegistry->getStrictType($definition->getFieldType());
            $l = $type->isLocaleAware() && $definition->isTranslatable() ? $language : IndexMappingUpdater::NO_LOCALE;
            $fullName = sprintf('attributes.%s.%s', $l, $fieldName);
            $addField($fullName);
        }
        $multiMatch->setFields($fields);

        $highlights = [];
        foreach ($fields as $field) {
            $highlights[$field] = new \stdClass();
        }
        $filterQuery->addMust($multiMatch);

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
            'fields' => $highlights,
        ]);

        $start = microtime(true);

        $search = $this->collectionIndex->createSearch($query);
        $search->addIndex($this->assetIndex);
        $result = $search->search();

        $searchTime = microtime(true) - $start;

        $indexTitles = [
            'asset_'.$this->kernelEnv => 'Asset',
            'collection_'.$this->kernelEnv => 'Collection',
        ];

        $result = new Pagerfanta(new ArrayAdapter(array_map(function (Result $result) use (
            $queryString,
            $indexTitles
        ): array {
            $value = $result->getSource()[self::SUGGEST_FIELD] ?? '';
            $hl = $result->getHighlights()[self::SUGGEST_FIELD.'.'.self::SUGGEST_SUB_FIELD] ?? $value;

            return [
                'id' => $result->getId(),
                'name' => $value,
                'hl' => $hl,
                't' => $indexTitles[preg_replace('#_\d{4}-\d{2}-\d{2}-\d{6}$#', '', $result->getIndex())],
            ];
        }, $result->getResults())));

        $esQuery = $query->toArray();

        return [$result, $esQuery, $searchTime];
    }
}
