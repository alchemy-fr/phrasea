<?php

declare(strict_types=1);

namespace App\Service\Asset\AssetPolicy;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\Collection;
use App\Repository\Core\AssetPolicyRepository;
use App\Repository\Core\CollectionRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Applies the asset policies of the current user to an asset (getPolicyApplicationFilter()), and
 * describes their "hide_attribute" actions for the searches (getHiddenAttributeScopes()): both
 * must agree on which assets a policy applies to (see matchesConditions()).
 */
final class AssetPolicyManager
{
    use SecurityAwareTrait;

    public const string ACTION_HIDE_RENDITION = 'hide_rendition';
    public const string ACTION_HIDE_ATTRIBUTE = 'hide_attribute';
    public const string CONDITION_COLLECTION = 'collection';

    private readonly CacheInterface $cache;

    public function __construct(
        private readonly AssetPolicyRepository $assetPolicyRepository,
        private readonly CollectionRepository $collectionRepository,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    public function getPolicyApplicationFilter(Asset $asset): AssetPolicyResultFilter
    {
        $user = $this->getUser();

        // Policies only depend on the workspace and the user: load them once per request.
        $policies = $this->cache->get(
            sprintf('%s_%s', $asset->getWorkspaceId(), $user?->getUserIdentifier() ?? '_anon'),
            fn (): array => $this->assetPolicyRepository->getAssetPolicies(
                $asset->getWorkspaceId(),
                $user?->getUserIdentifier(),
                $user?->getGroups() ?? []
            )
        );

        $filter = new AssetPolicyResultFilter();

        foreach ($policies as $policy) {
            if ($this->matchesConditions($asset, $policy->getConditions())) {
                $this->applyPolicyToOutput($policy->getActions(), $filter);
            }
        }

        return $filter;
    }

    /**
     * Where the "hide_attribute" policies of a user hide each attribute definition, for the
     * searches: every scope is the workspace of a policy and the absolute paths of the collections
     * that the reference collection of the asset must be, or be below, all of them (none: every
     * asset of the workspace). A policy whose condition names a collection that does not exist
     * applies to no asset and yields no scope.
     *
     * @return array<string, list<array{w: string, p: string[]}>> indexed by definition ID
     */
    public function getHiddenAttributeScopes(?string $userId, array $groupIds): array
    {
        if (null === $userId && empty($groupIds)) {
            return [];
        }

        return $this->cache->get('hidden_attributes_'.($userId ?? '_anon'), function () use ($userId, $groupIds): array {
            $scopes = [];
            foreach ($this->assetPolicyRepository->getAssetPolicies(null, $userId, $groupIds) as $policy) {
                $definitionIds = $this->getHiddenDefinitionIds($policy);
                if (empty($definitionIds)) {
                    continue;
                }

                $paths = $this->resolveCollectionPaths($policy->getConditions());
                if (null === $paths) {
                    continue;
                }

                $scope = ['w' => $policy->getWorkspaceId(), 'p' => $paths];
                foreach ($definitionIds as $definitionId) {
                    $scopes[$definitionId][] = $scope;
                }
            }

            return $scopes;
        });
    }

    /**
     * @return string[]
     */
    private function getHiddenDefinitionIds(AssetPolicy $policy): array
    {
        $ids = [];
        foreach ($policy->getActions() as $action) {
            if (self::ACTION_HIDE_ATTRIBUTE === ($action['action'] ?? null) && \is_string($action['definitionId'] ?? null)) {
                $ids[] = $action['definitionId'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The absolute paths that the reference collection of an asset must be, or be below, for the
     * conditions to match it (see matchesConditions()), null when no asset can match them.
     *
     * @return string[]|null
     */
    private function resolveCollectionPaths(array $conditions): ?array
    {
        $paths = [];
        foreach ($conditions as $condition) {
            $collectionId = self::getConditionCollectionId($condition);
            if (null === $collectionId) {
                continue;
            }
            if (!Uuid::isValid($collectionId)) {
                return null;
            }

            $collection = $this->collectionRepository->find($collectionId);
            if (!$collection instanceof Collection) {
                return null;
            }
            $paths[] = $collection->getAbsolutePath();
        }

        return array_values(array_unique($paths));
    }

    /**
     * A condition matches the assets whose reference collection is the given collection or is
     * below it. A condition this manager cannot evaluate (unknown field or operator, no value)
     * matches every asset: it must not make a policy hide less than its author meant.
     */
    private function matchesConditions(Asset $asset, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $collectionId = self::getConditionCollectionId($condition);
            if (null === $collectionId) {
                continue;
            }

            $path = $asset->getReferenceCollection()?->getAbsolutePath() ?? '';
            if (!\in_array($collectionId, explode('/', $path), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The collection ID of a "collection =" condition, null when the condition cannot be evaluated.
     */
    private static function getConditionCollectionId(mixed $condition): ?string
    {
        if (!\is_array($condition)
            || self::CONDITION_COLLECTION !== ($condition['field'] ?? null)
            || '=' !== ($condition['operator'] ?? null)
            || !\is_string($condition['value'] ?? null)
            || '' === $condition['value']
        ) {
            return null;
        }

        return $condition['value'];
    }

    private function applyPolicyToOutput(array $actions, AssetPolicyResultFilter $filter): void
    {
        foreach ($actions as $action) {
            switch ($action['action']) {
                case self::ACTION_HIDE_RENDITION:
                    $filter->addFilteredRendition($action['definitionId']);
                    break;
                case self::ACTION_HIDE_ATTRIBUTE:
                    $filter->addFilteredAttribute($action['definitionId']);
                    break;
            }
        }
    }
}
