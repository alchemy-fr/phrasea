<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\AQL\DocumentTextAnalyzer;
use Elastica\Query;

/**
 * The assets on which an asset policy hides an attribute definition from the user (see
 * AssetPolicyManager::getHiddenAttributeScopes()): those of its workspace whose reference
 * collection is, or is below, each of the given collections ("referenceCollectionPath" is
 * indexed with a path hierarchy analyzer: one token per ancestor path).
 *
 * @phpstan-type Scope array{w: string, p: string[]}
 */
final class HiddenAttributeScope
{
    /**
     * @param Scope $scope
     */
    public static function createQuery(array $scope): Query\AbstractQuery
    {
        $query = new Query\BoolQuery();
        $query->addFilter(new Query\Term(['workspaceId' => $scope['w']]));
        foreach ($scope['p'] as $path) {
            $query->addFilter(new Query\Term(['referenceCollectionPath' => $path]));
        }

        return $query;
    }

    /**
     * Whether the Elasticsearch document of an asset is in the scope: the evaluation of createQuery() in PHP.
     *
     * @param Scope $scope
     */
    public static function coversDocument(array $scope, array $document): bool
    {
        if (!in_array($scope['w'], (array) ($document['workspaceId'] ?? []), true)) {
            return false;
        }

        $tokens = [];
        foreach ((array) ($document['referenceCollectionPath'] ?? []) as $path) {
            if (is_string($path)) {
                array_push($tokens, ...DocumentTextAnalyzer::pathHierarchyTokens($path));
            }
        }

        return array_all($scope['p'], static fn (string $path): bool => in_array($path, $tokens, true));
    }

    /**
     * The documents outside every scope, null when there is no scope.
     *
     * @param list<Scope> $scopes
     */
    public static function createExclusionQuery(array $scopes): ?Query\BoolQuery
    {
        if (empty($scopes)) {
            return null;
        }

        $query = new Query\BoolQuery();
        foreach ($scopes as $scope) {
            $query->addMustNot(self::createQuery($scope));
        }

        return $query;
    }

    /**
     * Groups definitions hidden in the same scopes, so that each group is filtered once.
     *
     * @param string[]                   $definitionIds
     * @param array<string, list<Scope>> $hiddenScopes  by definition ID, see AssetPolicyManager::getHiddenAttributeScopes()
     *
     * @return list<array{definitionIds: string[], scopes: list<Scope>}>
     */
    public static function groupDefinitions(array $definitionIds, array $hiddenScopes): array
    {
        $groups = [];
        foreach ($definitionIds as $definitionId) {
            $scopes = $hiddenScopes[$definitionId] ?? [];
            $key = json_encode($scopes, JSON_THROW_ON_ERROR);
            $groups[$key] ??= ['definitionIds' => [], 'scopes' => $scopes];
            $groups[$key]['definitionIds'][] = $definitionId;
        }

        return array_values($groups);
    }

    /**
     * Whether a scope hides the definition from every asset of its workspace.
     *
     * @param list<Scope> $scopes
     */
    public static function coversWorkspace(array $scopes, string $workspaceId): bool
    {
        return array_any($scopes, static fn (array $scope): bool => $scope['w'] === $workspaceId && [] === $scope['p']);
    }

    /**
     * The scopes of the workspace of a definition: a policy only applies to the definitions of its workspace.
     *
     * @param array<string, list<Scope>> $hiddenScopes by definition ID
     *
     * @return list<Scope>
     */
    public static function ofDefinition(array $hiddenScopes, string $definitionId, string $workspaceId): array
    {
        return array_values(array_filter(
            $hiddenScopes[$definitionId] ?? [],
            static fn (array $scope): bool => $scope['w'] === $workspaceId,
        ));
    }
}
