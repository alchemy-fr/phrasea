<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Admin;

use Alchemy\ESBundle\Service\IndexRemover;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Configuration\IndexConfig;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Index\Resetter;

/**
 * Read & write access to the physical Elasticsearch indices and aliases behind
 * the logical FOS Elastica indices, for the EasyAdmin screens.
 *
 * Every mutation is executed synchronously against the cluster; callers are
 * expected to have confirmed the action with the user first.
 *
 * Every index or alias name received is checked (see assertManaged()): the admin can
 * never reach an index of another deployment sharing the cluster, even by crafted URL/form.
 */
final readonly class ESIndexAdmin
{
    public const string ROLE_ACTIVE = 'active';      // physical index currently aliased by a logical index
    public const string ROLE_DIRECT = 'direct';      // physical index used without alias (use_alias: false)
    public const string ROLE_OLD = 'old';            // dated index left behind by a previous populate
    public const string ROLE_SYSTEM = 'system';      // hidden ES index (".something")
    public const string ROLE_UNMANAGED = 'unmanaged'; // not related to any logical index of this app

    /**
     * A single concrete index or alias name: no list (","), wildcard ("*", "?"), date math ("<"),
     * exclusion ("-" first), "_all" or hidden (".") name, which would make Elasticsearch act on
     * other indices than the one named.
     */
    public const string NAME_PATTERN = '#^[a-z0-9][a-z0-9._-]*$#D';

    public function __construct(
        private IndexManager $indexManager,
        private ConfigManager $configManager,
        private Client $client,
        private Resetter $resetter,
        private IndexRemover $indexRemover,
        /**
         * Only indices/aliases starting with this prefix are listed and manageable ('' = everything).
         */
        private string $indexPrefix = '',
    ) {
    }

    public function getIndexPrefix(): string
    {
        return $this->indexPrefix;
    }

    public static function isValidName(string $name): bool
    {
        return 1 === preg_match(self::NAME_PATTERN, $name);
    }

    /**
     * Whether a physical index or alias name designates a single index or alias within the
     * managed scope (name prefix).
     */
    public function isManaged(string $name): bool
    {
        return self::isValidName($name) && $this->isInScope($name);
    }

    /**
     * @throws \InvalidArgumentException when the name is not a single index or alias name within the managed scope
     */
    public function assertManaged(string $name): void
    {
        if (!self::isValidName($name)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid index or alias name: only a single name made of lowercase letters, digits, ".", "_" and "-" (not first) is accepted.', $name));
        }
        if (!$this->isInScope($name)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is outside the managed scope: only indices and aliases prefixed with "%s" can be managed here.', $name, $this->indexPrefix));
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     target: string,
     *     useAlias: bool,
     *     exists: bool,
     *     activeIndices: list<string>,
     *     oldIndices: list<string>,
     *     docsCount: int,
     * }>
     */
    public function getLogicalIndices(): array
    {
        $physical = $this->getPhysicalIndices();
        $result = [];

        foreach ($this->getLogicalTargets() as $name => $target) {
            $active = [];
            $old = [];
            $docs = 0;
            foreach ($physical as $p) {
                if ($p['logicalIndex'] !== $name) {
                    continue;
                }
                if (self::ROLE_OLD === $p['role']) {
                    $old[] = $p['name'];
                } else {
                    $active[] = $p['name'];
                    $docs += $p['docsCount'];
                }
            }

            $result[] = [
                'name' => $name,
                'target' => $target,
                'useAlias' => $this->usesAlias($name),
                'exists' => [] !== $active,
                'activeIndices' => $active,
                'oldIndices' => $old,
                'docsCount' => $docs,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{
     *     name: string,
     *     health: string|null,
     *     status: string|null,
     *     docsCount: int,
     *     storeSize: int,
     *     primaries: int|null,
     *     replicas: int|null,
     *     createdAt: \DateTimeImmutable|null,
     *     aliases: list<string>,
     *     logicalIndex: string|null,
     *     role: string,
     * }>
     */
    public function getPhysicalIndices(): array
    {
        $params = [
            'format' => 'json',
            'bytes' => 'b',
            'h' => 'index,health,status,docs.count,store.size,pri,rep,creation.date',
        ];
        if ('' !== $this->indexPrefix) {
            $params['index'] = $this->indexPrefix.'*';
        }
        $rows = $this->client->cat()->indices($params)->asArray();

        $aliases = $this->getAliasesByIndex();
        $targets = $this->getLogicalTargets();

        $result = [];
        foreach ($rows as $row) {
            $name = $row['index'];
            if (!$this->isInScope($name)) {
                continue;
            }
            $indexAliases = $aliases[$name] ?? [];
            [$logical, $role] = $this->resolveRole($name, $indexAliases, $targets);

            $result[] = [
                'name' => $name,
                'health' => $row['health'] ?? null,
                'status' => $row['status'] ?? null,
                'docsCount' => (int) ($row['docs.count'] ?? 0),
                'storeSize' => (int) ($row['store.size'] ?? 0),
                'primaries' => isset($row['pri']) ? (int) $row['pri'] : null,
                'replicas' => isset($row['rep']) ? (int) $row['rep'] : null,
                'createdAt' => isset($row['creation.date']) ? (new \DateTimeImmutable())->setTimestamp(intdiv((int) $row['creation.date'], 1000)) : null,
                'aliases' => $indexAliases,
                'logicalIndex' => $logical,
                'role' => $role,
            ];
        }

        usort($result, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * @return array<string, list<string>> alias name => physical indices
     */
    public function getAliases(): array
    {
        $result = [];
        foreach ($this->getAliasesByIndex() as $index => $aliases) {
            foreach ($aliases as $alias) {
                $result[$alias][] = $index;
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * @return array{settings: array, mappings: array, aliases: array, stats: array}
     */
    public function getIndexDetails(string $indexName): array
    {
        $this->assertManaged($indexName);
        $info = $this->client->indices()->get(['index' => $indexName])->asArray()[$indexName] ?? [];
        $stats = $this->client->indices()->stats(['index' => $indexName])->asArray();

        return [
            'settings' => $info['settings'] ?? [],
            'mappings' => $info['mappings'] ?? [],
            'aliases' => array_keys($info['aliases'] ?? []),
            'stats' => $stats['indices'][$indexName]['total'] ?? [],
        ];
    }

    public function indexExists(string $indexName): bool
    {
        $this->assertManaged($indexName);

        return $this->client->indices()->exists(['index' => $indexName])->asBool();
    }

    public function deleteIndex(string $indexName): void
    {
        $this->assertManaged($indexName);
        $this->client->indices()->delete(['index' => $indexName]);
    }

    public function openIndex(string $indexName): void
    {
        $this->assertManaged($indexName);
        $this->client->indices()->open(['index' => $indexName]);
    }

    public function closeIndex(string $indexName): void
    {
        $this->assertManaged($indexName);
        $this->client->indices()->close(['index' => $indexName]);
    }

    public function refreshIndex(string $indexName): void
    {
        $this->assertManaged($indexName);
        $this->client->indices()->refresh(['index' => $indexName]);
    }

    public function addAlias(string $indexName, string $alias): void
    {
        $this->assertManaged($indexName);
        $this->assertManaged($alias);
        $this->updateAliases([
            ['add' => ['index' => $indexName, 'alias' => $alias]],
        ]);
    }

    public function removeAlias(string $indexName, string $alias): void
    {
        $this->assertManaged($indexName);
        $this->assertManaged($alias);
        $this->updateAliases([
            ['remove' => ['index' => $indexName, 'alias' => $alias]],
        ]);
    }

    /**
     * Atomically points $alias to $indexName only, removing it from every other index.
     *
     * @return list<string> the indices the alias was removed from
     */
    public function switchAlias(string $alias, string $indexName): array
    {
        $this->assertManaged($indexName);
        $this->assertManaged($alias);

        $previous = array_values(array_filter(
            $this->getAliases()[$alias] ?? [],
            fn (string $i): bool => $i !== $indexName,
        ));

        $actions = [];
        foreach ($previous as $p) {
            $actions[] = ['remove' => ['index' => $p, 'alias' => $alias]];
        }
        $actions[] = ['add' => ['index' => $indexName, 'alias' => $alias]];

        $this->updateAliases($actions);

        return $previous;
    }

    /**
     * Recreates the logical index empty, with the mapping from the configuration
     * (same as `fos:elastica:reset --index=<name>`).
     */
    public function resetLogicalIndex(string $logicalIndex): void
    {
        $this->assertLogicalIndex($logicalIndex);
        $this->resetter->resetIndex($logicalIndex);
    }

    /**
     * @return list<string> the removed physical indices
     */
    public function removeOldIndices(string $logicalIndex): array
    {
        $this->assertLogicalIndex($logicalIndex);
        $plan = $this->indexRemover->collectIndicesToRemove($logicalIndex, oldsOnly: true);
        $this->indexRemover->removePlannedIndices($plan);

        return array_keys($plan[$logicalIndex] ?? []);
    }

    public function isLogicalIndex(string $name): bool
    {
        return \array_key_exists($name, $this->indexManager->getAllIndexes());
    }

    private function assertLogicalIndex(string $name): void
    {
        if (!$this->isLogicalIndex($name)) {
            throw new \InvalidArgumentException(\sprintf('Unknown logical index "%s"', $name));
        }
    }

    /**
     * Listing scope (name prefix only): hidden system indices are listed, but not manageable.
     */
    private function isInScope(string $name): bool
    {
        return '' === $this->indexPrefix || str_starts_with($name, $this->indexPrefix);
    }

    private function usesAlias(string $logicalIndex): bool
    {
        $config = $this->configManager->getIndexConfiguration($logicalIndex);

        return $config instanceof IndexConfig && $config->isUseAlias();
    }

    /**
     * @return array<string, string> logical name => configured index/alias name
     */
    private function getLogicalTargets(): array
    {
        $targets = [];
        foreach ($this->indexManager->getAllIndexes() as $name => $index) {
            $targets[$name] = $index->getName();
        }

        return $targets;
    }

    /**
     * Managed indices only, with their managed aliases only.
     *
     * @return array<string, list<string>> physical index => aliases
     */
    private function getAliasesByIndex(): array
    {
        $result = [];
        foreach ($this->client->indices()->getAlias()->asArray() as $index => $data) {
            if (!$this->isInScope($index)) {
                continue;
            }
            $result[$index] = array_values(array_filter(
                array_keys($data['aliases'] ?? []),
                $this->isInScope(...),
            ));
        }

        return $result;
    }

    /**
     * @param list<string>          $aliases
     * @param array<string, string> $targets
     *
     * @return array{0: string|null, 1: string}
     */
    private function resolveRole(string $physicalName, array $aliases, array $targets): array
    {
        if (str_starts_with($physicalName, '.')) {
            return [null, self::ROLE_SYSTEM];
        }

        foreach ($targets as $logical => $target) {
            if ($physicalName === $target) {
                return [$logical, self::ROLE_DIRECT];
            }
            if (\in_array($target, $aliases, true)) {
                return [$logical, self::ROLE_ACTIVE];
            }
        }

        foreach ($targets as $logical => $target) {
            if (1 === preg_match('#^'.preg_quote($target, '#').'_\d{4}-\d{2}-\d{2}-\d{6}$#', $physicalName)) {
                return [$logical, self::ROLE_OLD];
            }
        }

        return [null, self::ROLE_UNMANAGED];
    }

    /**
     * @param list<array<string, array{index: string, alias: string}>> $actions
     */
    private function updateAliases(array $actions): void
    {
        $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);
    }
}
