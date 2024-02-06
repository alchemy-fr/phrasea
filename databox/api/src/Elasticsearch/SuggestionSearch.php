<?php

declare(strict_types=1);

namespace App\Elasticsearch;

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
        $multiMatch = new Query\MultiMatch();
        $multiMatch->setType('bool_prefix');
        $multiMatch->setQuery($queryString);
        $multiMatch->setFields([
            self::AUTOCOMPLETE_FIELD,
            self::AUTOCOMPLETE_FIELD.'._2gram',
            self::AUTOCOMPLETE_FIELD.'._3gram',
        ]);
        $filterQuery->addMust($multiMatch);

        $query = new Query();
        $query->setTrackTotalHits(false);
        $query->setQuery($filterQuery);

        $query->setSort([
            '_score' => 'DESC',
            'createdAt' => 'DESC',
        ]);

        $query->setSize(15);
        $start = microtime(true);

        $search = $this->collectionIndex->createSearch($query);
        $search->addIndex($this->assetIndex);
        $result = $search->search();

        $searchTime = microtime(true) - $start;

        $indexTitles = [
            'asset_'.$this->kernelEnv => 'Asset',
            'collection_'.$this->kernelEnv => 'Collection',
        ];

        $result = new Pagerfanta(new ArrayAdapter(array_map(function (Result $result) use ($queryString, $indexTitles): array {
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
        $words = array_map(fn (string $w): string => preg_quote($w, '#'), explode(' ', $query));

        return preg_replace('/('.implode('|', $words).')/i', "[hl]$1[/hl]", $value);
    }
}
