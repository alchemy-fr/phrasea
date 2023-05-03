<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Exception\AliasIsIndexException;
use RuntimeException;

class ElasticsearchClient
{
    public function __construct(private readonly Client $client)
    {
    }

    public function updateMapping(string $indexName, array $mapping): void
    {
        $this->client->request($indexName.'/_mapping',
            'PUT',
            $mapping
        );
    }

    /**
     * Returns the name of a single index that an alias points to or throws
     * an exception if there is more than one.
     *
     * @throws AliasIsIndexException
     */
    public function getAliasedIndex(string $aliasName): ?string
    {
        $aliasesInfo = $this->client->request('_aliases', 'GET')->getData();
        $aliasedIndexes = [];

        foreach ($aliasesInfo as $indexName => $indexInfo) {
            if ($indexName === $aliasName) {
                throw new AliasIsIndexException($indexName);
            }
            if (!isset($indexInfo['aliases'])) {
                continue;
            }

            $aliases = array_keys($indexInfo['aliases']);
            if (in_array($aliasName, $aliases, true)) {
                $aliasedIndexes[] = $indexName;
            }
        }

        if (count($aliasedIndexes) > 1) {
            throw new RuntimeException(sprintf('Alias "%s" is used for multiple indexes: ["%s"]. Make sure it\'s'.'either not used or is assigned to one index only', $aliasName, implode('", "', $aliasedIndexes)));
        }

        return array_shift($aliasedIndexes);
    }
}
