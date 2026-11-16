<?php

declare(strict_types=1);

namespace App\Tests\Functional\ElasticSearch;

use FOS\ElasticaBundle\Configuration\IndexConfig;
use FOS\ElasticaBundle\Configuration\ManagerInterface;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Index\Resetter;

/**
 * The test environment rebuilds its indices in place (elastica.use_alias: false): these
 * tests switch one index to the production layout, a dated physical index behind an alias,
 * and restore the plain index afterwards.
 */
trait AliasedIndexTestTrait
{
    private function useAlias(string $indexName): void
    {
        $this->setUseAlias($indexName, true);

        // the alias takes the name of the plain index
        $this->getEsClient()->indices()->delete(['index' => $this->getAliasName($indexName), 'ignore_unavailable' => true]);
    }

    private function restorePlainIndex(string $indexName): void
    {
        $alias = $this->getAliasName($indexName);
        $this->setUseAlias($indexName, false);

        $client = $this->getEsClient();
        foreach (array_keys($client->indices()->getAlias()->asArray()) as $physical) {
            if (1 === preg_match('#^'.preg_quote($alias, '#').'_\d{4}-\d{2}-\d{2}-\d{6}$#D', (string) $physical)) {
                $client->indices()->delete(['index' => (string) $physical]);
            }
        }

        /** @var Index $index */
        $index = static::getContainer()->get('fos_elastica.index.'.$indexName);
        $index->overrideName($alias);
        /** @var Resetter $resetter */
        $resetter = static::getContainer()->get(Resetter::class);
        $resetter->resetIndex($indexName);
    }

    private function setUseAlias(string $indexName, bool $useAlias): void
    {
        /** @var ManagerInterface $configManager */
        $configManager = static::getContainer()->get('fos_elastica.config_manager');
        $config = $configManager->getIndexConfiguration($indexName);
        (new \ReflectionProperty(IndexConfig::class, 'useAlias'))->setValue($config, $useAlias);
    }

    private function getEsClient(): Client
    {
        return static::getContainer()->get(Client::class);
    }

    /**
     * Configured name of the index, prefix and environment included: the name of the alias.
     */
    private function getAliasName(string $indexName): string
    {
        /** @var ManagerInterface $configManager */
        $configManager = static::getContainer()->get('fos_elastica.config_manager');

        return $configManager->getIndexConfiguration($indexName)->getElasticSearchName();
    }

    private function getAliasedIndex(string $indexName): ?string
    {
        $alias = $this->getAliasName($indexName);
        foreach ($this->getEsClient()->indices()->getAlias()->asArray() as $physical => $info) {
            if (isset($info['aliases'][$alias])) {
                return (string) $physical;
            }
        }

        return null;
    }

    /**
     * @return list<string> the dated physical indices of the index that no alias points to
     */
    private function getUnaliasedIndices(string $indexName): array
    {
        $alias = $this->getAliasName($indexName);
        $indices = [];
        foreach ($this->getEsClient()->indices()->getAlias()->asArray() as $physical => $info) {
            if (1 === preg_match('#^'.preg_quote($alias, '#').'_\d{4}-\d{2}-\d{2}-\d{6}$#D', (string) $physical) && empty($info['aliases'])) {
                $indices[] = (string) $physical;
            }
        }

        return $indices;
    }
}
