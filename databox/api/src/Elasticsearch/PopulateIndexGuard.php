<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\Exception\PopulateIndexNotReadyException;
use App\Elasticsearch\Mapping\IndexSyncState;
use FOS\ElasticaBundle\Configuration\ManagerInterface;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Protects the physical indices built by populates.
 *
 * Elasticsearch creates a missing index on the first document written to it, with a
 * dynamic mapping (keyword fields such as "users" / "groups" become "text": the ACL
 * filters stop matching). An index being built must therefore never be deleted by
 * another populate, and the alias must not be switched to an index that is not the
 * one the populate created.
 */
final readonly class PopulateIndexGuard
{
    /**
     * Suffix FOS Elastica appends to the configured name when it creates an aliased index.
     */
    private const string DATE_SUFFIX_PATTERN = '\d{4}-\d{2}-\d{2}-\d{6}';

    public function __construct(
        private IndexManager $indexManager,
        private Client $client,
        private IndexSyncState $indexSyncState,
        private PopulateLockManager $lockManager,
        #[Autowire(service: 'fos_elastica.config_manager')]
        private ManagerInterface $configManager,
    ) {
    }

    /**
     * The physical index the current process populates (once FOS Elastica has reset it),
     * null when the index has no alias: it is then rebuilt in place, under its own name.
     */
    public function getBuiltIndexName(string $indexName): ?string
    {
        if (!$this->configManager->getIndexConfiguration($indexName)->isUseAlias()) {
            return null;
        }

        return $this->indexManager->getIndex($indexName)->getName();
    }

    /**
     * Removes the unaliased physical indices left behind by previous populates, except
     * those still being built by a populate that is alive.
     *
     * The physical indices are matched on the configured name of the index: the name of
     * the Index service is the one of the last index populated by this process.
     */
    public function removeOrphanIndices(string $indexName): void
    {
        $alias = $this->configManager->getIndexConfiguration($indexName)->getElasticSearchName();
        $pattern = '#^'.preg_quote($alias, '#').'_'.self::DATE_SUFFIX_PATTERN.'$#D';

        foreach ($this->client->indices()->getAlias()->asArray() as $physical => $info) {
            $physical = (string) $physical;
            if (1 !== preg_match($pattern, $physical) || [] !== ($info['aliases'] ?? [])) {
                continue;
            }
            if ($this->lockManager->isBuilding($physical)) {
                continue;
            }

            $this->client->indices()->delete(['index' => $physical]);
        }
    }

    /**
     * Removes the index built by a failed populate, unless the alias already points to it.
     */
    public function removeBuiltIndex(string $physicalIndexName): void
    {
        if (!$this->exists($physicalIndexName)) {
            return;
        }

        $aliases = $this->client->indices()->getAlias(['index' => $physicalIndexName])->asArray();
        if ([] === ($aliases[$physicalIndexName]['aliases'] ?? [])) {
            $this->client->indices()->delete(['index' => $physicalIndexName]);
        }
    }

    /**
     * FOS Elastica renames the Index service to the index it builds: once that index is
     * abandoned, the writes this process still makes must go through the alias again.
     */
    public function restoreIndexName(string $indexName): void
    {
        $config = $this->configManager->getIndexConfiguration($indexName);
        $index = $this->indexManager->getIndex($indexName);
        if ($config->isUseAlias() && $index->getName() !== $config->getElasticSearchName()) {
            $index->overrideName($config->getElasticSearchName());
        }
    }

    public function exists(string $physicalIndexName): bool
    {
        return 200 === $this->client->indices()->exists(['index' => $physicalIndexName])->getStatusCode();
    }

    /**
     * @throws PopulateIndexNotReadyException when the index is gone, or does not have the configured mapping
     */
    public function assertReadyToSwitch(string $indexName, string $physicalIndexName): void
    {
        if (!$this->exists($physicalIndexName)) {
            throw new PopulateIndexNotReadyException(\sprintf('The index "%s" was deleted while being populated', $physicalIndexName));
        }

        $mappings = $this->client->indices()->getMapping(['index' => $physicalIndexName])->asArray();
        $actual = $mappings[$physicalIndexName]['mappings'] ?? [];
        $expected = $this->indexSyncState->getCurrentConfigMapping($indexName)['mappings'] ?? [];

        // FOS Elastica writes the model in the mapping it creates, a dynamic mapping has none
        $expectedModel = $expected['_meta']['model'] ?? null;
        if (null !== $expectedModel && ($actual['_meta']['model'] ?? null) !== $expectedModel) {
            throw new PopulateIndexNotReadyException(\sprintf('The index "%s" was not created by the populate (recreated by a write after its deletion?): its mapping has no model', $physicalIndexName));
        }

        $mismatches = [];
        foreach ($expected['properties'] ?? [] as $field => $definition) {
            $expectedType = $definition['type'] ?? 'object';
            $actualType = isset($actual['properties'][$field]) ? ($actual['properties'][$field]['type'] ?? 'object') : null;
            if ($expectedType !== $actualType) {
                $mismatches[] = \sprintf('%s: %s instead of %s', $field, $actualType ?? 'missing', $expectedType);
            }
        }
        if ([] !== $mismatches) {
            throw new PopulateIndexNotReadyException(\sprintf('The mapping of the index "%s" is not the configured one (%s)', $physicalIndexName, implode(', ', $mismatches)));
        }
    }
}
