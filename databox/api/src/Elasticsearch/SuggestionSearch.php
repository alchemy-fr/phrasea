<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Elasticsearch\Query\MatchBoolPrefix;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use Elastica\Aggregation\Filters;
use Elastica\Aggregation\TopHits;
use Elastica\Query;
use Elastica\Result;
use FOS\ElasticaBundle\Elastica\Index;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;

class SuggestionSearch extends AbstractSearch
{
    private const AUTOCOMPLETE_FIELD = 'autocomplete';

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

        /** @var AttributeDefinition[] $autocompleteAttributes */
        $autocompleteAttributes = $this->em->getRepository(AttributeDefinition::class)
            ->getSearchableAttributes($userId, $groupIds, [
                AttributeDefinitionRepositoryInterface::OPT_SUGGEST_ENABLED => true,
            ]);

        $match = new Query\BoolQuery();
        $addField = function (string $f) use ($match, $queryString): void {
            $boolPrefix = new MatchBoolPrefix($f, $queryString);
            $match->addShould($boolPrefix);
        };

        $addField(self::AUTOCOMPLETE_FIELD);

        $language = $options['locale'] ?? '*';

        foreach ($autocompleteAttributes as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldName($definition).'.autocomplete';
            $type = $this->typeRegistry->getStrictType($definition->getFieldType());
            $l = $type->isLocaleAware() && $definition->isTranslatable() ? $language : IndexMappingUpdater::NO_LOCALE;
            $addField(sprintf('attributes.%s.%s', $l, $fieldName));

        }
        $filterQuery->addMust($match);

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
                'autocomplete' => [
                    'fragment_size' => 255,
                    'number_of_fragments' => 1,
                ],
                'attributes.*' => [
                    'type' => 'unified',
                ],
            ],
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
            $value = $result->getSource()[self::AUTOCOMPLETE_FIELD] ?? '';

            return [
                'id' => $result->getId(),
                'name' => $value,
                'hl' => $this->highlight($queryString, $value),
                't' => $indexTitles[$result->getIndex()],
            ];
        }, $result->getResults())));

        $esQuery = $query->toArray();

        return [$result, $esQuery, $searchTime];
    }

    private function highlight(string $query, string $value): string
    {
        $query = trim(preg_replace('#\s+#', ' ', $query));
        $words = array_map(function (string $w): string {
            $w = preg_replace('#^"(.+)"$#', '$1', trim($w));

            return preg_quote($w, '#');

        }, explode(' ', $query));

        return preg_replace('/('.implode('|', $words).')/i', "[hl]$1[/hl]", $value);
    }
}
